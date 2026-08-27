#!/bin/bash

echo "📦 Inyectando el Plugin Importador de Tarifas en la infraestructura..."

# Aseguramos que la carpeta mu-plugins exista
mkdir -p /var/www/html/wp-content/mu-plugins

# Inyectamos el código PHP directamente al contenedor
cat << 'EOF' > /var/www/html/wp-content/mu-plugins/importador-tarifas-ubigeo.php
<?php
/*
Plugin Name: Importador de Tarifas de Envío CSV
Description: Agrega un panel en WooCommerce para subir y procesar tarifas de envío dinámicas.
Author: Arquitectura WP
Version: 1.0
*/

// 1. Registrar el submenú en WooCommerce
add_action( 'admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Tarifas de Envío (CSV)',
        'Tarifas de Envío',
        'manage_woocommerce',
        'wc-shipping-csv',
        'wc_shipping_csv_page'
    );
});

// 2. Interfaz y lógica de procesamiento
function wc_shipping_csv_page() {
    if ( isset( $_POST['upload_csv_nonce'] ) && wp_verify_nonce( $_POST['upload_csv_nonce'], 'upload_shipping_csv' ) ) {
        if ( ! empty( $_FILES['shipping_csv']['tmp_name'] ) ) {
            $file = $_FILES['shipping_csv']['tmp_name'];
            $handle = fopen( $file, "r" );
            
            if ( $handle !== FALSE ) {
                $rates = array();
                fgetcsv( $handle, 1000, "," ); 
                
                while ( ( $data = fgetcsv( $handle, 1000, "," ) ) !== FALSE ) {
                    if ( count( $data ) < 4 ) continue;
                    
                    $distrito = mb_strtoupper( trim( $data[0] ), 'UTF-8' );
                    $rates[$distrito] = array(
                        "ubigeo" => trim( $data[1] ),
                        "costo"  => floatval( $data[2] ),
                        "gratis" => floatval( $data[3] )
                    );
                }
                fclose( $handle );
                
                update_option( 'wc_custom_shipping_rates', $rates );
                WC_Cache_Helper::get_transient_version( 'shipping', true );
                
                echo '<div class="notice notice-success is-dismissible"><p>✅ ¡Éxito! Se importaron ' . count( $rates ) . ' distritos correctamente.</p></div>';
            }
        }
    }
    
    ?>
    <div class="wrap">
        <h1>Actualizar Tarifas de Envío</h1>
        <p>Sube tu archivo CSV manteniendo esta estructura de columnas: <strong>Distrito, Ubigeo, Costo Regular, Monto Mínimo Gratis</strong>.</p>
        
        <div class="card" style="max-width: 500px; margin-top: 20px; padding: 20px;">
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'upload_shipping_csv', 'upload_csv_nonce' ); ?>
                <input type="file" name="shipping_csv" accept=".csv" required style="margin-bottom: 15px; display: block;" />
                <?php submit_button( 'Subir y Actualizar Tarifas', 'primary' ); ?>
            </form>
        </div>
    </div>
    <?php
}
EOF

echo "✅ ¡Plugin Importador instalado exitosamente en mu-plugins!"