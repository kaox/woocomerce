# 🚀 Tienda WooCommerce Automatizada con Docker y WP-CLI

Este proyecto despliega un entorno completo de WordPress y WooCommerce utilizando Docker Compose. Está diseñado para realizar una instalación desatendida (Zero-Touch) que configura la tienda, importa productos, establece tarifas de envío por distritos y aplica un diseño profesional sin necesidad de hacer clics manuales.

## ✨ Características Principales

*   **Instalación Silenciosa:** Configura WordPress, base de datos y credenciales automáticamente.
*   **Diseño Profesional:** Instala y activa la plantilla *Brandstore* de Astra.
*   **Catálogo Automático:** Importa productos de forma masiva desde un archivo CSV.
*   **Páginas Legales:** Genera páginas (Términos, Políticas) a partir de archivos HTML locales.
*   **Envíos Dinámicos:** Configura zonas de envío y tarifas condicionales (ej. Envío Gratis por compras mayores a S/.100) en distritos de Perú.
*   **Asistente Omitido:** Deshabilita las pantallas de bienvenida de WooCommerce para ir directo al grano.

## 📁 Estructura del Proyecto

```text
📁 tu-proyecto/
 ├── 📄 docker-compose.yml  # Orquestador de contenedores (WP, DB, WP-CLI)
 ├── 📄 .env                # Variables de configuración y credenciales
 ├── 📄 init.sh             # Script principal de automatización
 ├── 📄 shipping.sh         # Submódulo para reglas de envío y distritos
 ├── 📁 data/               
 │    └── 📄 productos.csv  # Archivo de importación de WooCommerce
 └── 📁 pages/              
      └── 📄 *.html         # Código fuente para tus páginas legales
```

## 🛠️ Requisitos Previos

- Docker instalado.
- Docker Compose instalado.

## ⚙️ Configuración
Variables: Edita el archivo .env con el nombre de tu sitio y tus datos de administrador.

Fragmento de código
WP_PLUGINS=woocommerce elementor wordpress-seo contact-form-7 astra-sites
WP_THEME=astra
SITE_URL=http://localhost:8080
SITE_TITLE="Mi Tienda Automática"
WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=admin123
WP_ADMIN_EMAIL=admin@tudominio.com

Productos: Reemplaza el archivo data/productos.csv con tu propio catálogo respetando las cabeceras estándar de WooCommerce.

Envíos: Si deseas modificar las tarifas o agregar distritos, edita el array DISTRIBUTION_LIST dentro del archivo shipping.sh.


2. Páginas Personalizadas (HTML)
Coloca el código HTML de las páginas que deseas crear automáticamente dentro de la carpeta pages/. Puedes crear tantos archivos como necesites (ej. privacidad.html, nosotros.html).

Asegúrate de que estas páginas estén declaradas en tu script init.sh:

Bash
wp post create /scripts/pages/terminos.html --post_type=page --post_title="Términos y Condiciones" --post_status=publish
3. Plantillas de Astra (Opcional)
Si utilizas Astra y deseas importar un Starter Template, revisa el archivo init.sh y asegúrate de cambiar "brandstore" por el ID de la plantilla que prefieras en la siguiente línea:

Bash
wp astra-sites import "brandstore" --yes 
🚀 Uso y Ejecución
Abre tu terminal en la carpeta raíz del proyecto.

Si estás en un entorno basado en Unix (Linux/macOS), dale permisos de ejecución al script:

Bash
chmod +x init.sh
Levanta los contenedores en segundo plano:

Bash
docker compose up -d
La instalación automática tomará alrededor de 1-2 minutos. Puedes ver el progreso en tiempo real mirando los logs del automatizador:

Bash
docker compose logs -f wp-cli
Cuando veas el mensaje "✅ ¡Instalación automática completada con éxito!" en la terminal, presiona Ctrl + C para salir de los logs.

Accede a tu sitio web desde tu navegador:

Sitio público: http://localhost:8080

Panel de administración: http://localhost:8080/wp-admin (usa el usuario y contraseña definidos en tu archivo .env).

🧹 Detener y Limpiar
Para detener el servidor:

Bash
docker compose stop
Para destruir los contenedores (esto no borrará tus datos guardados en los volúmenes, como la base de datos o las subidas de WordPress):

Bash
docker compose down
Para borrar todo y empezar desde cero (incluyendo la base de datos):

Bash
docker compose down -v

***


https://secure.micuentaweb.pe/vads-merchant/

https://secure.micuentaweb.pe/doc/es-PE/plugins/

https://secure.micuentaweb.pe/doc/es-PE/plugins/woocommerce/sitemap.html

https://www.youtube.com/watch?v=oshicDacA3A

