#!/bin/bash

echo "📦 Iniciando importación de productos en módulo independiente..."

# 1. Ejecutar importador
wp eval '
  require_once WC()->plugin_path() . "/includes/import/class-wc-product-csv-importer.php";
  $importer = new WC_Product_CSV_Importer( "/scripts/data/productos.csv", array(
    "parse" => true,
  ) );
  $results = $importer->import();
  echo "✅ Importación PHP finalizada. Exitosos: " . count( $results["imported"] ) . "\n";
' --user=${WP_ADMIN_USER}

# 2. Forzar actualización de todas las tablas y cachés de visibilidad
echo "🔄 Refrescando tablas y contadores de visibilidad..."
wp wc tool run regenerate_product_lookup_tables --user=${WP_ADMIN_USER}
wp wc tool run recount_terms --user=${WP_ADMIN_USER}
wp wc tool run clear_transients --user=${WP_ADMIN_USER}
wp transient delete --all

echo "✅ Catálogo procesado y visible."