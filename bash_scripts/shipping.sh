#!/bin/bash

echo "🧹 Limpiando zonas de envío conflictivas..."
wp eval '
    $zones = WC_Shipping_Zones::get_zones();
    foreach ( $zones as $zone ) {
        $z = new WC_Shipping_Zone( $zone["id"] );
        $z->delete();
    }
' --user=${WP_ADMIN_USER}

echo "🌍 Creando una Zona Única para Perú (Todo el país)..."
wp eval '
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

echo "🧠 Inyectando lógica avanzada (Lectura de Nombres Reales)..."
mkdir -p /var/www/html/wp-content/mu-plugins
cat << 'EOF' > /var/www/html/wp-content/mu-plugins/ubigeo-shipping-fix.php
<?php
/*
Plugin Name: Fix Envíos por Distrito Avanzado
Description: Captura el nombre real del distrito (no el ID) y asigna tarifas.
*/

// 1. JavaScript para capturar el TEXTO del select y forzar actualización
add_action( 'wp_footer', function() {
    if ( is_checkout() && ! is_wc_endpoint_url() ) {
        echo "<script>
        jQuery(document).ready(function($){
            // Crear un campo oculto para guardar el nombre real del distrito
            if($('#distrito_nombre_real').length === 0) {
                $('form.checkout').append('<input type=\"hidden\" name=\"distrito_nombre_real\" id=\"distrito_nombre_real\" value=\"\">');
            }
            
            function actualizarNombreDistrito() {
                var select = $('#billing_distrito');
                if(select.length === 0) select = $('#billing_city'); // Fallback
                
                if(select.is('select')) {
                    var texto = select.find('option:selected').text();
                    if(texto && texto.toLowerCase().indexOf('seleccione') === -1) {
                        $('#distrito_nombre_real').val(texto);
                    }
                }
            }

            // Cada vez que cambie un desplegable, actualizamos el texto y recalculamos
            $(document).on('change', 'select[name^=\"billing_\"], select.ubigeo-peru, #billing_distrito', function() {
                actualizarNombreDistrito();
                $('body').trigger('update_checkout');
            });
            
            setTimeout(actualizarNombreDistrito, 1000); // Ejecutar al cargar la página
        });
        </script>";
    }
});

// 2. Interceptar los datos antes de calcular el envío
add_filter( 'woocommerce_cart_shipping_packages', 'ubigeo_forzar_recalculo_distrito', 99 );
function ubigeo_forzar_recalculo_distrito( $packages ) {
    if ( isset( $_POST['post_data'] ) ) {
        parse_str( $_POST['post_data'], $post_data );
        
        // Prioridad 1: Leer nuestro campo oculto que contiene el TEXTO puro ("Miraflores")
        if ( !empty($post_data['distrito_nombre_real']) ) {
            $packages[0]['destination']['distrito'] = trim($post_data['distrito_nombre_real']);
        } elseif ( !empty($post_data['billing_distrito']) ) {
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

// 3. Aplicar las tarifas correctas
add_filter( 'woocommerce_package_rates', 'ubigeo_tarifas_dinamicas', 99, 2 );
function ubigeo_tarifas_dinamicas( $rates, $package ) {
    $distrito = isset( $package['destination']['distrito'] ) ? strtoupper( trim( $package['destination']['distrito'] ) ) : '';
    $departamento = isset( $package['destination']['state'] ) ? strtoupper( trim( $package['destination']['state'] ) ) : '';
    
    // Identificadores de Lima
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
                    $rates[$rate_key]->label = 'Envío a ' . ucwords(strtolower($distrito));
                    
                    // LÓGICA DE PRECIOS EXACTA
                    if ( in_array( $distrito, array('MIRAFLORES', 'SAN ISIDRO', 'SANTIAGO DE SURCO', 'SURCO', 'SAN BORJA') ) ) {
                        $rates[$rate_key]->cost = 10.00;
                    } elseif ( in_array( $distrito, array('CHORRILLOS', 'BARRANCO', 'LA MOLINA') ) ) {
                        $rates[$rate_key]->cost = 12.00;
                    } else {
                        // Comas, SJL, SMP, etc.
                        $rates[$rate_key]->cost = 15.00; 
                    }
                }
            }
        } else {
            // Es Provincia
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
echo "✅ ¡Corrección de nombres reales aplicada!"