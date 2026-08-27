#!/bin/bash

echo "📦 Iniciando importación de productos avanzada..."

wp eval '
  $file = "/scripts/data/productos.csv";

  if ( ! file_exists( $file ) ) {
      echo "❌ El archivo no existe en: " . $file . "\n";
      exit;
  }

  // 1. Eliminar caracteres BOM invisibles de Excel en UTF-8
  $content = file_get_contents( $file );
  $content = preg_replace( "/^\xEF\xBB\xBF/", "", $content );
  file_put_contents( $file, $content );

  // 2. Autenticar usuario dentro del contexto PHP
  $user_login = "'"${WP_ADMIN_USER}"'";
  $user = get_user_by( "login", $user_login ) ?: get_user_by( "id", 1 );
  if ( $user ) {
      wp_set_current_user( $user->ID );
  }

  // 3. Cargar dependencias de WooCommerce
  if ( ! defined( "WC_ABSPATH" ) ) { define( "WC_ABSPATH", dirname( WC_PLUGIN_FILE ) . "/" ); }
  require_once WC_ABSPATH . "includes/admin/importers/class-wc-product-csv-importer-controller.php";
  require_once WC_ABSPATH . "includes/import/class-wc-product-csv-importer.php";

  // 4. Mapeo nativo de columnas
  $headers = array();
  if ( ( $handle = fopen( $file, "r" ) ) !== false ) {
      $headers = fgetcsv( $handle, 0, "," );
      fclose( $handle );
  }

  include_once WC_ABSPATH . "includes/admin/importers/mappings/mappings.php";
  $mapping = wc_importer_default_english_mappings( $headers );
  $mapping = wc_importer_default_special_english_mappings( $mapping );

  // 5. Ejecutar la importación
  $importer = new WC_Product_CSV_Importer( $file, array(
      "mapping"          => $mapping,
      "parse"            => true,
      "update_existing"  => true,
      "prevent_timeouts" => false,
  ) );

  $results = $importer->import();
  echo "✅ Importados exitosamente: " . count( $results["imported"] ) . "\n";
  if ( ! empty( $results["failed"] ) ) {
      echo "❌ Fallidos: " . count( $results["failed"] ) . "\n";
  }
' --user=${WP_ADMIN_USER}

echo "🔄 Refrescando tablas y contadores..."
wp wc tool run regenerate_product_lookup_tables --user=${WP_ADMIN_USER}
wp wc tool run recount_terms --user=${WP_ADMIN_USER}
wp wc tool run clear_transients --user=${WP_ADMIN_USER}
wp transient delete --all

echo "✅ Catálogo procesado y visible."