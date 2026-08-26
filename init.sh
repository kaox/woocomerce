#!/bin/sh

echo "⏳ Esperando 20 segundos para que la BD y WordPress se inicialicen..."
sleep 20

echo "⚙️ Instalando el núcleo de WordPress..."
wp core install --url=${SITE_URL} --title="${SITE_TITLE}" --admin_user=${WP_ADMIN_USER} --admin_password=${WP_ADMIN_PASSWORD} --admin_email=${WP_ADMIN_EMAIL} --skip-email

echo "🔌 Instalando y activando plugins: ${WP_PLUGINS} ..."
wp plugin install ${WP_PLUGINS} --activate

echo "⚙️ Configurando opciones iniciales de WooCommerce..."
wp option update woocommerce_onboarding_profile '{"completed":true}' --format=json
wp option update woocommerce_task_list_hidden "yes"
wp option update woocommerce_default_country "PE:LIM"
wp option update woocommerce_currency "PEN"

echo "🎨 Instalando y activando el tema: ${WP_THEME} ..."
wp theme install ${WP_THEME} --activate

# --- NUEVA SECCIÓN PARA ASTRA ---
echo "🚀 Importando el template de Astra (Esto puede tardar un par de minutos)..."

# Astra necesita que le indiques el ID de la plantilla que quieres instalar.
# El parámetro --yes es para que acepte la importación sin preguntarte [Y/n] en la consola.
wp astra-sites import organic-shop-02 --yes

echo "📦 Importando catálogo de productos desde el CSV..."
wp eval '
  require_once WC()->plugin_path() . "/includes/import/class-wc-product-csv-importer.php";
  $importer = new WC_Product_CSV_Importer( "/scripts/data/productos.csv", array(
    "parse" => true,
  ) );
  $results = $importer->import();
  echo "✅ Importación finalizada. Exitosos: " . count( $results["imported"] ) . " | Fallidos: " . count( $results["failed"] ) . "\n";
' --user=${WP_ADMIN_USER}

echo "⚙️ Configurando opciones iniciales de WooCommerce..."
wp eval '
  update_option( "woocommerce_onboarding_profile", array( "completed" => true, "skipped" => true ) );
  update_option( "woocommerce_task_list_hidden", "yes" );
  update_option( "woocommerce_extended_task_list_hidden", "yes" );
  update_option( "woocommerce_task_list_welcome_modal_dismissed", "yes" );
  update_option( "woocommerce_task_list_tracked_completed_tasks", array( "store_details", "products", "tax", "shipping", "payments" ) );
  update_option( "woocommerce_default_country", "PE:LIM" );
  update_option( "woocommerce_currency", "PEN" );
' --user=${WP_ADMIN_USER}

echo "📄 Creando páginas automáticas..."
# Crear Libro de Reclamaciones
wp post create --post_type=page --post_title="Libro de Reclamaciones" --post_content="Aquí va el formulario de tu libro de reclamaciones." --post_status=publish

# Crear Políticas de Envío
wp post create --post_type=page --post_title="Políticas de Envío" --post_content="Nuestros envíos tardan entre 24 y 48 horas..." --post_status=publish

# Crear Términos y Condiciones
wp post create /scripts/pages/terminos-y-condiciones.html --post_type=page --post_title="Términos y Condiciones" --post_status=publish
wp post create /scripts/pages/cambios-y-devoluciones.html --post_type=page --post_title="Cambios y Devoluciones" --post_status=publish
wp post create /scripts/pages/politicas-de-proteccion-de-datos-personales.html --post_type=page --post_title="Políticas de Protección de Datos Personales" --post_status=publish

echo "✅ ¡Instalación automática completada con éxito!"