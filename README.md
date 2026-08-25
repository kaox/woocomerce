# 🚀 Infraestructura Automatizada de WordPress con Docker

Este proyecto levanta un entorno completo de WordPress utilizando Docker Compose. Además, automatiza la instalación inicial utilizando **WP-CLI**, configurando automáticamente temas, plugins (como WooCommerce), plantillas de Astra y creando páginas personalizadas a partir de archivos HTML locales sin intervención manual.

## 📁 Estructura del Proyecto

Asegúrate de que tus archivos estén organizados de la siguiente manera antes de iniciar:

```text
📁 tu-proyecto/
 ├── 📄 docker-compose.yml  # Configuración de los contenedores Docker
 ├── 📄 .env                # Variables de entorno (Credenciales, plugins y tema)
 ├── 📄 init.sh             # Script de automatización de WP-CLI
 └── 📁 pages/              # Carpeta para tus páginas en formato HTML
      └── 📄 terminos.html  # Ejemplo: Código HTML para la página de Términos
```

## 🛠️ Requisitos Previos

- Docker instalado.
- Docker Compose instalado.

## ⚙️ Configuración
1. Variables de Entorno (.env)
Abre el archivo .env y configura los datos de tu sitio, credenciales de base de datos y los plugins/tema que deseas instalar.

Fragmento de código
WP_PLUGINS=woocommerce elementor wordpress-seo contact-form-7 astra-sites
WP_THEME=astra
SITE_URL=http://localhost:8080
SITE_TITLE="Mi Tienda Automática"
WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=admin123
WP_ADMIN_EMAIL=admin@tudominio.com
...
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
