#!/bin/sh

echo "⏳ Esperando 20 segundos para que la BD y WordPress se inicialicen..."
sleep 20

echo "⚙️ Instalando el núcleo de WordPress..."
wp core install --url=${SITE_URL} --title="${SITE_TITLE}" --admin_user=${WP_ADMIN_USER} --admin_password=${WP_ADMIN_PASSWORD} --admin_email=${WP_ADMIN_EMAIL} --skip-email

echo "🔌 Instalando y activando plugins: ${WP_PLUGINS} ..."
wp plugin install ${WP_PLUGINS} --activate

echo "🎨 Instalando y activando el tema: ${WP_THEME} ..."
wp theme install ${WP_THEME} --activate

# --- NUEVA SECCIÓN PARA ASTRA ---
echo "🚀 Importando el template de Astra (Esto puede tardar un par de minutos)..."

# Astra necesita que le indiques el ID de la plantilla que quieres instalar.
# El parámetro --yes es para que acepte la importación sin preguntarte [Y/n] en la consola.
wp astra-sites import "horticulture" --yes

echo "📄 Creando páginas automáticas..."
# Crear Libro de Reclamaciones
wp post create --post_type=page --post_title="Libro de Reclamaciones" --post_content="Aquí va el formulario de tu libro de reclamaciones." --post_status=publish

# Crear Políticas de Envío
wp post create --post_type=page --post_title="Políticas de Envío" --post_content="Nuestros envíos tardan entre 24 y 48 horas..." --post_status=publish

# Crear Términos y Condiciones
wp post create /scripts/pages/terminos.html --post_type=page --post_title="Términos y Condiciones" --post_status=publish

echo "✅ ¡Instalación automática completada con éxito!"