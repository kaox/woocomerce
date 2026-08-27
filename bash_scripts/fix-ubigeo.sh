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
    
    $instance_local = $zone->add_shipping_method( "flat_rate" );
    update_option( "woocommerce_flat_rate_" . $instance_local . "_settings", array(
        "title" => "Envío Local", "tax_status" => "none", "cost" => "15"
    ) );
    
    $instance_prov = $zone->add_shipping_method( "flat_rate" );
    update_option( "woocommerce_flat_rate_" . $instance_prov . "_settings", array(
        "title" => "Envío a Provincia (Pago en Destino)", "tax_status" => "none", "cost" => "0"
    ) );
' --user=${WP_ADMIN_USER}

echo "🧠 Inyectando Interceptor AJAX con lectura dinámica de CSV..."
mkdir -p /var/www/html/wp-content/mu-plugins
cat << 'EOF' > /var/www/html/wp-content/mu-plugins/ubigeo-shipping-fix.php
<?php
/*
Plugin Name: Fix Envíos - Integración CSV
Description: Extrae el distrito real y calcula tarifas basadas en un archivo CSV.
*/

// 1. Interceptar AJAX (Se mantiene intacto del script avanzado)
add_action( 'wp_footer', function() {
    if ( is_checkout() && ! is_wc_endpoint_url() ) {
        ?>
        <script>
        jQuery(document).ready(function($){
            $.ajaxSetup({
                beforeSend: function(jqXHR, settings) {
                    if (settings.url && settings.url.indexOf('wc-ajax=update_order_review') !== -1) {
                        var selectDistrito = $('#billing_distrito');
                        if(selectDistrito.length > 0 && selectDistrito.is('select')) {
                            var nombreDistrito = selectDistrito.find('option:selected').text();
                            if(nombreDistrito && nombreDistrito.toLowerCase().indexOf('seleccione') === -1) {
                                settings.data += '&real_distrito=' + encodeURIComponent(nombreDistrito.trim());
                            }
                        }
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
        if ( isset( $_POST['real_distrito'] ) && !empty( $_POST['real_distrito'] ) ) {
            $packages[0]['destination']['distrito'] = sanitize_text_field( wp_unslash( $_POST['real_distrito'] ) );
        } elseif ( !empty($post_data['billing_distrito']) ) {
            $packages[0]['destination']['distrito'] = $post_data['billing_distrito'];
        }
        if ( isset( $_POST['real_provincia'] ) && !empty( $_POST['real_provincia'] ) ) {
            $packages[0]['destination']['city'] = sanitize_text_field( wp_unslash( $_POST['real_provincia'] ) );
        } elseif ( !empty($post_data['billing_provincia']) ) {
            $packages[0]['destination']['city'] = $post_data['billing_provincia'];
        }
        if ( !empty($post_data['billing_departamento']) ) {
            $packages[0]['destination']['state'] = $post_data['billing_departamento'];
        }
    }
    return $packages;
}

// 3. Lógica con CSV Dinámico
add_filter( 'woocommerce_package_rates', 'ubigeo_tarifas_dinamicas', 99, 2 );
function ubigeo_tarifas_dinamicas( $rates, $package ) {
    $distrito = isset( $package['destination']['distrito'] ) ? mb_strtoupper( trim( $package['destination']['distrito'] ), 'UTF-8' ) : '';
    $provincia = isset( $package['destination']['city'] ) ? mb_strtoupper( trim( $package['destination']['city'] ), 'UTF-8' ) : '';
    $departamento = isset( $package['destination']['state'] ) ? mb_strtoupper( trim( $package['destination']['state'] ), 'UTF-8' ) : '';

    // Cargar tarifas del CSV guardadas en BD
    $custom_rates = get_option('wc_custom_shipping_rates', array());
    
    $es_lima_departamento = in_array( $departamento, array('LIMA', 'LIM', 'LMA', '15', 'PE:LMA', 'PE:LIM') );
    $es_lima_metropolitana = false;
    
    if ( $es_lima_departamento ) {
        if ( $provincia !== '' && !in_array($provincia, array('LIMA', 'LIM', 'PROVINCIA DE LIMA')) ) {
            $es_lima_metropolitana = false;
        } else {
            $es_lima_metropolitana = true;
        }
    }
    // Si el distrito está en nuestro CSV, asumimos que es Lima Metropolitana
    if ( isset($custom_rates[$distrito]) ) {
        $es_lima_metropolitana = true;
    }

    $cart_total = WC()->cart->get_displayed_subtotal();

    foreach ( $rates as $rate_key => $rate ) {
        $label_lower = strtolower( $rate->label );
        $es_shalom = ( strpos( $label_lower, 'provincia' ) !== false || strpos( $label_lower, 'destino' ) !== false );

        if ( $es_lima_metropolitana ) {
            if ( $es_shalom ) unset( $rates[$rate_key] ); 
            else {
                if ( isset($custom_rates[$distrito]) ) {
                    $tarifa = $custom_rates[$distrito];
                    
                    if ( $tarifa['gratis'] > 0 && $cart_total >= $tarifa['gratis'] ) {
                        $rates[$rate_key]->cost = 0;
                        $rates[$rate_key]->label = 'Envío Gratis a ' . ucwords(strtolower($distrito));
                    } else {
                        $rates[$rate_key]->cost = $tarifa['costo'];
                        $rates[$rate_key]->label = 'Envío a ' . ucwords(strtolower($distrito));
                    }
                } else {
                    // Fallback si no está en CSV
                    $rates[$rate_key]->cost = 15.00; 
                    $rates[$rate_key]->label = 'Envío Local';
                }
            }
        } else {
            if ( ! $es_shalom ) unset( $rates[$rate_key] ); 
        }
    }
    return $rates;
}
EOF
echo "✅ ¡Motor lógico instalado!"