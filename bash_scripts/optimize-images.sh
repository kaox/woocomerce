#!/bin/bash

echo "🚀 Ejecutando submódulo de optimización y registro de imágenes..."

wp eval '
  require_once( ABSPATH . "wp-admin/includes/image.php" );
  require_once( ABSPATH . "wp-admin/includes/file.php" );
  require_once( ABSPATH . "wp-admin/includes/media.php" );

  $source_dir = "/scripts/data/raw_images";
  $target_dir = "/var/www/html/wp-content/uploads/catalog";

  if ( ! file_exists( $target_dir ) ) {
      mkdir( $target_dir, 0755, true );
  }

  if ( ! file_exists( $source_dir ) ) {
      echo "❌ La carpeta de origen no existe: " . $source_dir . "\n";
      exit;
  }

  // 1. Leer archivos de origen (Soporta JPG, JPEG, PNG y WEBP)
  $allowed_exts = array( "jpg", "jpeg", "png", "webp" );
  $files = array();
  $dir_items = scandir( $source_dir );

  if ( is_array( $dir_items ) ) {
      foreach ( $dir_items as $item ) {
          if ( $item === "." || $item === ".." ) continue;
          $ext = strtolower( pathinfo( $item, PATHINFO_EXTENSION ) );
          if ( in_array( $ext, $allowed_exts, true ) ) {
              $files[] = $source_dir . "/" . $item;
          }
      }
  }

  if ( empty( $files ) ) {
      echo "⚠️ No se encontraron imágenes en: " . $source_dir . "\n";
      exit;
  }

  echo "🖼️ Procesando " . count( $files ) . " imágenes e indexando en Medios...\n";

  $upload_dir = wp_upload_dir();

  foreach ( $files as $file ) {
      $filename = pathinfo( $file, PATHINFO_FILENAME );
      $target_webp = $target_dir . "/" . $filename . ".webp";

      $info = @getimagesize( $file );
      if ( ! $info ) continue;

      $mime = $info["mime"];
      $image = null;

      // Cargar imagen según formato de entrada
      switch ( $mime ) {
          case "image/jpeg":
              $image = @imagecreatefromjpeg( $file );
              break;
          case "image/png":
              $image = @imagecreatefrompng( $file );
              break;
          case "image/webp":
              $image = @imagecreatefromwebp( $file );
              break;
          default:
              continue 2;
      }

      if ( ! $image ) continue;

      // 2. Redimensionar si el ancho supera 1200px
      $max_width = 1200;
      $width = imagesx( $image );
      $height = imagesy( $image );

      if ( $width > $max_width ) {
          $new_width = $max_width;
          $new_height = (int) ( $height * ( $max_width / $width ) );
          $resized = imagecreatetruecolor( $new_width, $new_height );

          if ( $mime === "image/png" || $mime === "image/webp" ) {
              imagealphablending( $resized, false );
              imagesavealpha( $resized, true );
          }

          imagecopyresampled( $resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height );
          imagedestroy( $image );
          $image = $resized;
      } else if ( $mime === "image/png" || $mime === "image/webp" ) {
          imagealphablending( $image, true );
          imagesavealpha( $image, true );
      }

      // 3. Guardar archivo WebP en disco
      imagewebp( $image, $target_webp, 80 );
      imagedestroy( $image );

      // 4. Registrar en la Biblioteca de Medios de WordPress
      $file_url = $upload_dir["baseurl"] . "/catalog/" . $filename . ".webp";
      $attachment_id = attachment_url_to_postid( $file_url );

      if ( ! $attachment_id ) {
          $attachment = array(
              "guid"           => $file_url,
              "post_mime_type" => "image/webp",
              "post_title"     => $filename,
              "post_content"   => "",
              "post_status"    => "inherit"
          );

          $attachment_id = wp_insert_attachment( $attachment, $target_webp );
          $attach_data = wp_generate_attachment_metadata( $attachment_id, $target_webp );
          wp_update_attachment_metadata( $attachment_id, $attach_data );

          echo "✅ Registrada en Medios (ID " . $attachment_id . "): " . $filename . ".webp\n";
      } else {
          echo "✅ Ya registrada previamente (ID " . $attachment_id . "): " . $filename . ".webp\n";
      }
  }

  echo "🚀 ¡Todas las imágenes procesadas y visibles en la Biblioteca de Medios!\n";
' --user=${WP_ADMIN_USER}