#!/bin/bash

echo "🚀 Ejecutando submódulo de optimización (Forzando 1000x1000 1:1)..."

wp eval '
  require_once( ABSPATH . "wp-admin/includes/image.php" );
  require_once( ABSPATH . "wp-admin/includes/file.php" );
  require_once( ABSPATH . "wp-admin/includes/media.php" );

  $source_dir = "/scripts/data/raw_images";
  $target_dir = "/var/www/html/wp-content/uploads/catalog";

  if ( ! file_exists( $target_dir ) ) { mkdir( $target_dir, 0755, true ); }
  if ( ! file_exists( $source_dir ) ) { echo "❌ Carpeta no existe\n"; exit; }

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

  $upload_dir = wp_upload_dir();

  foreach ( $files as $file ) {
      $filename = pathinfo( $file, PATHINFO_FILENAME );
      $target_webp = $target_dir . "/" . $filename . ".webp";

      $info = @getimagesize( $file );
      if ( ! $info ) continue;

      $mime = $info["mime"];
      $image = null;

      switch ( $mime ) {
          case "image/jpeg": $image = @imagecreatefromjpeg( $file ); break;
          case "image/png":  $image = @imagecreatefrompng( $file ); break;
          case "image/webp": $image = @imagecreatefromwebp( $file ); break;
          default: continue 2;
      }
      if ( ! $image ) continue;

      // 2. FORZAR 1000x1000 SIN IMPORTAR EL TAMAÑO ORIGINAL
      $target_size = 1000;
      $width = imagesx( $image );
      $height = imagesy( $image );

      if ( $width > $height ) {
          $crop_size = $height;
          $crop_x = ( $width - $height ) / 2;
          $crop_y = 0;
      } else {
          $crop_size = $width;
          $crop_x = 0;
          $crop_y = ( $height - $width ) / 2;
      }

      $resized = imagecreatetruecolor( $target_size, $target_size );

      if ( $mime === "image/png" || $mime === "image/webp" ) {
          imagealphablending( $resized, false );
          imagesavealpha( $resized, true );
          $transparent = imagecolorallocatealpha( $resized, 255, 255, 255, 127 );
          imagefill( $resized, 0, 0, $transparent );
      } else {
          $white = imagecolorallocate( $resized, 255, 255, 255 );
          imagefill( $resized, 0, 0, $white );
      }

      // Copia y fuerza la escala a 1000x1000 (Upscale / Downscale)
      imagecopyresampled( $resized, $image, 0, 0, $crop_x, $crop_y, $target_size, $target_size, $crop_size, $crop_size );
      imagedestroy( $image );
      $image = $resized;

      // 3. Guardar archivo
      imagewebp( $image, $target_webp, 80 );
      imagedestroy( $image );

      // 4. Registro en WP
      $file_url = $upload_dir["baseurl"] . "/catalog/" . $filename . ".webp";
      $attachment_id = attachment_url_to_postid( $file_url );

      if ( ! $attachment_id ) {
          $attachment = array(
              "guid"           => $file_url,
              "post_mime_type" => "image/webp",
              "post_title"     => $filename,
              "post_status"    => "inherit"
          );
          $attachment_id = wp_insert_attachment( $attachment, $target_webp );
          $attach_data = wp_generate_attachment_metadata( $attachment_id, $target_webp );
          wp_update_attachment_metadata( $attachment_id, $attach_data );
          echo "✅ Registrada: " . $filename . ".webp\n";
      } else {
          echo "✅ Ya registrada: " . $filename . ".webp\n";
      }
  }
' --user=${WP_ADMIN_USER}