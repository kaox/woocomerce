#!/bin/bash

# Ruta al archivo CSV dentro del contenedor
CSV_PATH="/scripts/data/shipping_rates.csv" 

echo "📦 Importando tarifas de envío dinámicas desde CSV..."

wp eval '
    $file = "'"$CSV_PATH"'";
    if (!file_exists($file)) {
        echo "❌ Error: No se encontró el archivo CSV en $file\n";
        return;
    }
    
    $csv = array_map("str_getcsv", file($file));
    $headers = array_shift($csv); // Omitir la primera línea (cabeceras)
    $rates = array();
    
    foreach($csv as $row) {
        if(count($row) < 4) continue;
        
        $distrito = mb_strtoupper(trim($row[0]), "UTF-8");
        $rates[$distrito] = array(
            "ubigeo" => trim($row[1]),
            "costo"  => floatval($row[2]),
            "gratis" => floatval($row[3])
        );
    }
    
    // Guardar en la base de datos de WordPress
    update_option("wc_custom_shipping_rates", $rates);
    echo "✅ Se importaron " . count($rates) . " tarifas de distritos correctamente.\n";
' --user=${WP_ADMIN_USER}

echo "🧹 Limpiando caché de envíos..."
wp wc tool run clear_transients --user=${WP_ADMIN_USER}
wp transient delete --all