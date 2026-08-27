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

echo "🧠 Inyectando soporte para Lima Provincias (Huaura, Cañete, etc.)..."
mkdir -p /var/www/html/wp-content/mu-plugins
cat << 'EOF' > /var/www/html/wp-content/mu-plugins/ubigeo-shipping-fix.php
<?php
/*
Plugin Name: Fix Envíos - Interceptor AJAX Avanzado
Description: Extrae el distrito y la provincia real para diferenciar Lima Metropolitana de Lima Provincias.
*/

// 1. Interceptar la comunicación AJAX
add_action( 'wp_footer', function() {
    if ( is_checkout() && ! is_wc_endpoint_url() ) {
        ?>
        <script>
        jQuery(document).ready(function($){
            $.ajaxSetup({
                beforeSend: function(jqXHR, settings) {
                    if (settings.url && settings.url.indexOf('wc-ajax=update_order_review') !== -1) {
                        
                        // Capturar Distrito
                        var selectDistrito = $('#billing_distrito');
                        if(selectDistrito.length > 0 && selectDistrito.is('select')) {
                            var nombreDistrito = selectDistrito.find('option:selected').text();
                            if(nombreDistrito && nombreDistrito.toLowerCase().indexOf('seleccione') === -1) {
                                settings.data += '&real_distrito=' + encodeURIComponent(nombreDistrito.trim());
                            }
                        }
                        
                        // Capturar Provincia (Huaura, Huaral, Lima, etc.)
                        var selectProvincia = $('#billing_provincia');
                        if(selectProvincia.length === 0) selectProvincia = $('#billing_city');
                        if(selectProvincia.length > 0 && selectProvincia.is('select')) {
                            var nombreProvincia = selectProvincia.find('option:selected').text();
                            if(nombreProvincia && nombreProvincia.toLowerCase().indexOf('seleccione') === -1) {
                                settings.data += '&real_provincia=' + encodeURIComponent(nombreProvincia.trim());
                            }
                        }
                    }
                }
            });

            // Forzar actualización al cambiar cualquier desplegable
            $(document).on('change', 'select[name^=\"billing_\"], select.ubigeo-peru', function() {
                $('body').trigger('update_checkout');
            });
        });
        </script>
        <?php
    }
});

// 2. Leer los datos interceptados
add_filter( 'woocommerce_cart_shipping_packages', 'ubigeo_forzar_recalculo_distrito', 99 );
function ubigeo_forzar_recalculo_distrito( $packages ) {
    if ( isset( $_POST['post_data'] ) ) {
        parse_str( $_POST['post_data'], $post_data );
        
        // Asignar Distrito
        if ( isset( $_POST['real_distrito'] ) && !empty( $_POST['real_distrito'] ) ) {
            $packages[0]['destination']['distrito'] = sanitize_text_field( wp_unslash( $_POST['real_distrito'] ) );
        } elseif ( !empty($post_data['billing_distrito']) ) {
            $packages[0]['destination']['distrito'] = $post_data['billing_distrito'];
        }

        // Asignar Provincia
        if ( isset( $_POST['real_provincia'] ) && !empty( $_POST['real_provincia'] ) ) {
            $packages[0]['destination']['city'] = sanitize_text_field( wp_unslash( $_POST['real_provincia'] ) );
        } elseif ( !empty($post_data['billing_provincia']) ) {
            $packages[0]['destination']['city'] = $post_data['billing_provincia'];
        } elseif ( !empty($post_data['billing_city']) ) {
            $packages[0]['destination']['city'] = $post_data['billing_city'];
        }

        // Asignar Departamento
        if ( !empty($post_data['billing_departamento']) ) {
            $packages[0]['destination']['state'] = $post_data['billing_departamento'];
        } elseif ( !empty($post_data['billing_state']) ) {
            $packages[0]['destination']['state'] = $post_data['billing_state'];
        }
    }
    return $packages;
}

// 3. Lógica de Negocio: Lima Met. vs Provincias
add_filter( 'woocommerce_package_rates', 'ubigeo_tarifas_dinamicas', 99, 2 );
function ubigeo_tarifas_dinamicas( $rates, $package ) {
    $distrito = isset( $package['destination']['distrito'] ) ? strtoupper( trim( $package['destination']['distrito'] ) ) : '';
    $provincia = isset( $package['destination']['city'] ) ? strtoupper( trim( $package['destination']['city'] ) ) : '';
    $departamento = isset( $package['destination']['state'] ) ? strtoupper( trim( $package['destination']['state'] ) ) : '';
    
    $es_lima_departamento = in_array( $departamento, array('LIMA', 'LIM', 'LMA', '15', 'PE:LMA', 'PE:LIM') );
    
    $es_lima_metropolitana = false;
    
    // Si el departamento es Lima, revisamos la Provincia
    if ( $es_lima_departamento ) {
        // Si hay una provincia seleccionada, y NO es "Lima", entonces es Lima Provincias (Shalom)
        if ( $provincia !== '' && !in_array($provincia, array('LIMA', 'LIM', 'PROVINCIA DE LIMA')) ) {
            $es_lima_metropolitana = false;
        } else {
            // Si la provincia es Lima, o no hay provincia definida, asumimos Lima Met.
            $es_lima_metropolitana = true;
        }
    }

    // Seguro anti-fallos para los distritos principales
    if ( in_array( $distrito, array('MIRAFLORES', 'SAN ISIDRO', 'SURCO', 'SANTIAGO DE SURCO', 'SAN BORJA', 'LIMA', 'CHORRILLOS', 'BARRANCO', 'LA MOLINA') ) ) {
        $es_lima_metropolitana = true;
    }

    foreach ( $rates as $rate_key => $rate ) {
        $label_lower = strtolower( $rate->label );
        $es_shalom = ( strpos( $label_lower, 'provincia' ) !== false || strpos( $label_lower, 'shalom' ) !== false );

        if ( $es_lima_metropolitana ) {
            if ( $es_shalom ) {
                unset( $rates[$rate_key] ); 
            } else {
                if ( $distrito ) {
                    if ( is_numeric($distrito) ) {
                        $rates[$rate_key]->label = 'Envío Local (Lima)';
                    } else {
                        $rates[$rate_key]->label = 'Envío a ' . ucwords(strtolower($distrito));
                    }
                    
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
            // Si NO es Lima Metropolitana (Ej. Huaura, Arequipa, Cusco)
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
echo "✅ ¡Soporte para Lima Provincias activado!"