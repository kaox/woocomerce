#!/bin/bash

echo "🌍 Asegurando Zona Única para Perú..."
wp eval '
    $zones = WC_Shipping_Zones::get_zones();
    foreach ( $zones as $zone ) {
        $z = new WC_Shipping_Zone( $zone["id"] );
        $z->delete();
    }
    
    $zone = new WC_Shipping_Zone();
    $zone->set_zone_name( "Perú (Nacional)" );
    $zone->add_location( "PE", "country" );
    $zone->save();
    $zone_id = $zone->get_id();
    
    $instance_local = $zone->add_shipping_method( "flat_rate" );
    update_option( "woocommerce_flat_rate_" . $instance_local . "_settings", array(
        "title"      => "Envío Local",
        "tax_status" => "none",
        "cost"       => "15"
    ) );
    
    $instance_prov = $zone->add_shipping_method( "flat_rate" );
    update_option( "woocommerce_flat_rate_" . $instance_prov . "_settings", array(
        "title"      => "Envío a Provincia (Pago en Destino vía Shalom)",
        "tax_status" => "none",
        "cost"       => "0"
    ) );
' --user=${WP_ADMIN_USER}

echo "🧠 Inyectando Interceptor AJAX para extraer nombres reales..."
mkdir -p /var/www/html/wp-content/mu-plugins
cat << 'EOF' > /var/www/html/wp-content/mu-plugins/ubigeo-shipping-fix.php
<?php
/*
Plugin Name: Fix Envíos - Interceptor AJAX
Description: Extrae el nombre real del distrito antes de que WooCommerce calcule el envío.
*/

// 1. Interceptar la comunicación AJAX en el navegador
add_action( 'wp_footer', function() {
    if ( is_checkout() && ! is_wc_endpoint_url() ) {
        ?>
        <script>
        jQuery(document).ready(function($){
            // Espiar las peticiones de WooCommerce
            $.ajaxSetup({
                beforeSend: function(jqXHR, settings) {
                    if (settings.url && settings.url.indexOf('wc-ajax=update_order_review') !== -1) {
                        var selectDistrito = $('#billing_distrito');
                        if(selectDistrito.length > 0 && selectDistrito.is('select')) {
                            // Capturar el TEXTO visual (ej. "MIRAFLORES") y no el value (ej. "1272")
                            var nombreReal = selectDistrito.find('option:selected').text();
                            if(nombreReal && nombreReal.toLowerCase().indexOf('seleccione') === -1) {
                                // Inyectar el nombre real a la fuerza en la petición
                                settings.data += '&real_distrito=' + encodeURIComponent(nombreReal.trim());
                            }
                        }
                    }
                }
            });

            // Forzar actualización cuando se toca el selector de distrito
            $(document).on('change', '#billing_distrito, select.ubigeo-peru', function() {
                $('body').trigger('update_checkout');
            });
        });
        </script>
        <?php
    }
});

// 2. Leer la petición interceptada en el servidor
add_filter( 'woocommerce_cart_shipping_packages', 'ubigeo_forzar_recalculo_distrito', 99 );
function ubigeo_forzar_recalculo_distrito( $packages ) {
    if ( isset( $_POST['post_data'] ) ) {
        parse_str( $_POST['post_data'], $post_data );
        
        // Prioridad 1: Usar el nombre real que capturó nuestro JS
        if ( isset( $_POST['real_distrito'] ) && !empty( $_POST['real_distrito'] ) ) {
            $packages[0]['destination']['distrito'] = sanitize_text_field( wp_unslash( $_POST['real_distrito'] ) );
        } 
        // Prioridad 2: Fallback al comportamiento por defecto
        elseif ( !empty($post_data['billing_distrito']) ) {
            $packages[0]['destination']['distrito'] = $post_data['billing_distrito'];
        }

        // Asegurar departamento
        if ( !empty($post_data['billing_departamento']) ) {
            $packages[0]['destination']['state'] = $post_data['billing_departamento'];
        } elseif ( !empty($post_data['billing_state']) ) {
            $packages[0]['destination']['state'] = $post_data['billing_state'];
        }
    }
    return $packages;
}

// 3. Calcular tarifas con el nombre correcto
add_filter( 'woocommerce_package_rates', 'ubigeo_tarifas_dinamicas', 99, 2 );
function ubigeo_tarifas_dinamicas( $rates, $package ) {
    $distrito = isset( $package['destination']['distrito'] ) ? strtoupper( trim( $package['destination']['distrito'] ) ) : '';
    $departamento = isset( $package['destination']['state'] ) ? strtoupper( trim( $package['destination']['state'] ) ) : '';
    
    // Identificadores comunes para Lima
    $es_lima = in_array( $departamento, array('LIMA', 'LIM', 'LMA', '15', 'PE:LMA', 'PE:LIM') ) || 
               in_array( $distrito, array('MIRAFLORES', 'SAN ISIDRO', 'SURCO', 'SAN BORJA', 'LIMA', 'CHORRILLOS', 'BARRANCO', 'LA MOLINA') );

    foreach ( $rates as $rate_key => $rate ) {
        $label_lower = strtolower( $rate->label );
        $es_shalom = ( strpos( $label_lower, 'provincia' ) !== false || strpos( $label_lower, 'shalom' ) !== false );

        if ( $es_lima ) {
            if ( $es_shalom ) {
                unset( $rates[$rate_key] ); 
            } else {
                if ( $distrito ) {
                    // Si por alguna razón sigue pasando un número, mostramos un nombre limpio
                    if ( is_numeric($distrito) ) {
                        $rates[$rate_key]->label = 'Envío Local (Lima)';
                    } else {
                        $rates[$rate_key]->label = 'Envío a ' . ucwords(strtolower($distrito));
                    }
                    
                    // 🎯 Precios Exactos
                    if ( in_array( $distrito, array('MIRAFLORES', 'SAN ISIDRO', 'SANTIAGO DE SURCO', 'SURCO', 'SAN BORJA') ) ) {
                        $rates[$rate_key]->cost = 10.00;
                    } elseif ( in_array( $distrito, array('CHORRILLOS', 'BARRANCO', 'LA MOLINA') ) ) {
                        $rates[$rate_key]->cost = 12.00;
                    } else {
                        $rates[$rate_key]->cost = 15.00; 
                    }
                }
            }
        } else {
            // Si es Provincia, quitamos el envío local
            if ( ! $es_shalom ) {
                unset( $rates[$rate_key] ); 
            }
        }
    }
    return $rates;
}
EOF

echo "🧹 Limpiando caché..."
wp wc tool run clear_transients --user=${WP_ADMIN_USER}
wp transient delete --all
echo "✅ ¡Interceptor AJAX desplegado con éxito!"