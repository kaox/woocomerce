#!/bin/sh

echo "⏳ Esperando 20 segundos para que la BD y WordPress se inicialicen..."
sleep 20

echo "⚙️ Instalando el núcleo de WordPress..."
wp core install --url=${SITE_URL} --title="${SITE_TITLE}" --admin_user=${WP_ADMIN_USER} --admin_password=${WP_ADMIN_PASSWORD} --admin_email=${WP_ADMIN_EMAIL} --skip-email

echo "🔌 Instalando y activando plugins: ${WP_PLUGINS} ..."
wp plugin install ${WP_PLUGINS} --activate

echo "⚙️ Configurando opciones iniciales de WooCommerce..."
wp eval '
  update_option( "woocommerce_onboarding_profile", array( "completed" => true, "skipped" => true ) );
  update_option( "woocommerce_task_list_hidden", "yes" );
  update_option( "woocommerce_extended_task_list_hidden", "yes" );
  update_option( "woocommerce_task_list_welcome_modal_dismissed", "yes" );
  update_option( "woocommerce_task_list_tracked_completed_tasks", array( "store_details", "products", "tax", "shipping", "payments" ) );
  
  // Moneda y ubicación por defecto
  update_option( "woocommerce_default_country", "PE:LIM" );
  update_option( "woocommerce_currency", "PEN" );
  
// 📍 Restringir ventas y envíos SOLO a Perú
  update_option( "woocommerce_allowed_countries", "specific" );
  update_option( "woocommerce_specific_allowed_countries", array( "PE" ) );
  update_option( "woocommerce_ship_to_countries", "specific" ); 
  update_option( "woocommerce_specific_ship_to_countries", array( "PE" ) );
' --user=${WP_ADMIN_USER}


echo "🎨 Instalando y activando el tema: ${WP_THEME} ..."
wp theme install ${WP_THEME} --activate

# --- NUEVA SECCIÓN PARA ASTRA ---
echo "🚀 Importando el template de Astra (Esto puede tardar un par de minutos)..."

# Astra necesita que le indiques el ID de la plantilla que quieres instalar.
# El parámetro --yes es para que acepte la importación sin preguntarte [Y/n] en la consola.
wp astra-sites import brandstore --yes

echo "🔄 Regenerando tablas de búsqueda y desactivando asistente..."
wp wc tool run regenerate_product_lookup_tables --user=${WP_ADMIN_USER}
wp transient delete --all
wp option update woocommerce_onboarding_profile '{"completed":true,"skipped":true}' --format=json
wp option update woocommerce_task_list_hidden "yes"
wp option update woocommerce_extended_task_list_hidden "yes"

echo "🌐 Configurando el idioma a Español..."
wp language core install es_ES --activate
wp language plugin install --all es_ES
wp language theme install --all es_ES

echo "📄 Creando páginas automáticas..."
# Crear Libro de Reclamaciones
wp post create --post_type=page --post_title="Libro de Reclamaciones" --post_content="Aquí va el formulario de tu libro de reclamaciones." --post_status=publish

# Crear Políticas de Envío
wp post create --post_type=page --post_title="Políticas de Envío" --post_content="Nuestros envíos tardan entre 24 y 48 horas..." --post_status=publish

# Crear Términos y Condiciones
wp post create /scripts/pages/terminos-y-condiciones.html --post_type=page --post_title="Términos y Condiciones" --post_status=publish
wp post create /scripts/pages/cambios-y-devoluciones.html --post_type=page --post_title="Cambios y Devoluciones" --post_status=publish
wp post create /scripts/pages/politicas-de-proteccion-de-datos-personales.html --post_type=page --post_title="Políticas de Protección de Datos Personales" --post_status=publish

echo "🔗 Actualizando enlaces permanentes (Permalinks)..."
wp rewrite structure '/%postname%/' --hard
wp rewrite flush

echo "📦 Ejecutando submódulo de productos..."
chmod +x /scripts/import-products.sh
bash /scripts/import-products.sh

echo "📦 Ejecutando submódulo de envíos..."
chmod +x /scripts/shipping.sh
bash /scripts/shipping.sh

echo "📦 Ejecutando submódulo de métodos de pago..."
chmod +x /scripts/payment-methods.sh
bash /scripts/payment-methods.sh

echo "📦 Ejecutando submódulo de fix ubigeo..."
chmod +x /scripts/fix-ubigeo.sh
bash /scripts/fix-ubigeo.sh

echo "✅ ¡Instalación automática completada con éxito!"