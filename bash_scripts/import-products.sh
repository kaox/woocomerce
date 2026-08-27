#!/bin/bash

echo "📦 Iniciando importación de productos avanzada..."

wp eval '
  $csv_file = "/scripts/data/productos.csv";

  if ( ! file_exists( $csv_file ) ) {
      echo "❌ CSV no encontrado: " . $csv_file . "\n";
      exit;
  }

  // ── Autenticación ────────────────────────────────────────────────────────────
  $user = get_user_by( "login", "'"${WP_ADMIN_USER}"'" ) ?: get_user_by( "id", 1 );
  if ( $user ) wp_set_current_user( $user->ID );

  // ── Mapa basename → attachment_id ────────────────────────────────────────────
  $att_map = array();
  foreach ( get_posts( array( "post_type" => "attachment", "post_status" => "inherit", "posts_per_page" => -1 ) ) as $att ) {
      $att_map[ basename( wp_get_attachment_url( $att->ID ) ) ] = $att->ID;
  }
  echo "🖼️  Imágenes en Medios: " . count( $att_map ) . "\n";

  // ── Parsear CSV (fgetcsv maneja comillas y comas internas) ────────────────────
  $rows = array();
  $handle = fopen( $csv_file, "r" );
  $header = fgetcsv( $handle, 0, "," );
  // Limpiar BOM
  $header[0] = preg_replace( "/^\xEF\xBB\xBF/", "", $header[0] );
  while ( ( $line = fgetcsv( $handle, 0, "," ) ) !== false ) {
      if ( count( $line ) === count( $header ) ) {
          $rows[] = array_combine( $header, $line );
      }
  }
  fclose( $handle );
  echo "📄 Filas en CSV: " . count( $rows ) . "\n";

  // ── Helper: obtener o crear categoría con jerarquía ─────────────────────────
  function ensure_category( $path_str ) {
      $parts     = array_map( "trim", explode( ">", $path_str ) );
      $parent_id = 0;
      $leaf_id   = 0;
      foreach ( $parts as $name ) {
          $term = get_term_by( "name", $name, "product_cat" );
          if ( $term ) {
              $leaf_id = $term->term_id;
          } else {
              $result  = wp_insert_term( $name, "product_cat", array( "parent" => $parent_id ) );
              $leaf_id = is_wp_error( $result ) ? 0 : $result["term_id"];
          }
          $parent_id = $leaf_id;
      }
      return $leaf_id;
  }

  // ── Helper: registrar atributo global si no existe ───────────────────────────
  function ensure_attribute_taxonomy( $name ) {
      $slug     = wc_sanitize_taxonomy_name( $name );
      $taxonomy = wc_attribute_taxonomy_name( $name );
      if ( ! taxonomy_exists( $taxonomy ) ) {
          wc_create_attribute( array( "name" => $name, "slug" => $slug, "type" => "select", "order_by" => "menu_order", "has_archives" => false ) );
          register_taxonomy( $taxonomy, array( "product", "product_variation" ) );
      }
      return $taxonomy;
  }

  // ── Helper: obtener o crear término de atributo ──────────────────────────────
  function ensure_term( $value, $taxonomy ) {
      $term = get_term_by( "name", $value, $taxonomy );
      if ( $term ) return $term->term_id;
      $result = wp_insert_term( $value, $taxonomy );
      return is_wp_error( $result ) ? 0 : $result["term_id"];
  }

  // ── Preparar atributos de un row del CSV ─────────────────────────────────────
  function build_attributes( $row, $is_variable ) {
      $attrs = array();
      for ( $i = 1; $i <= 2; $i++ ) {
          $aname = trim( $row[ "Attribute $i name" ] ?? "" );
          $avals = trim( $row[ "Attribute $i value(s)" ] ?? "" );
          if ( ! $aname || ! $avals ) continue;

          $taxonomy = ensure_attribute_taxonomy( $aname );
          $values   = array_filter( array_map( "trim", explode( ",", $avals ) ) );
          $term_ids = array_filter( array_map( function( $v ) use ( $taxonomy ) { return ensure_term( $v, $taxonomy ); }, $values ) );

          $attr = new WC_Product_Attribute();
          $attr->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
          $attr->set_name( $taxonomy );
          $attr->set_options( array_values( $term_ids ) );
          $attr->set_visible( (bool) ( $row[ "Attribute $i visible" ] ?? 1 ) );
          $attr->set_variation( $is_variable );
          $attrs[] = $attr;
      }
      return $attrs;
  }

  // ── PASO 1: productos simple y variable ──────────────────────────────────────
  $sku_to_id = array();
  $imported  = 0;
  $failed    = 0;

  foreach ( $rows as $row ) {
      $type = strtolower( trim( $row["Type"] ?? "" ) );
      if ( ! in_array( $type, array( "simple", "variable" ) ) ) continue;

      $sku = trim( $row["SKU"] );

      // Si ya existe, registrar ID y continuar
      $existing_id = wc_get_product_id_by_sku( $sku );
      if ( $existing_id ) {
          $sku_to_id[ $sku ] = $existing_id;
          echo "⏭️  Ya existe (ID $existing_id): $sku\n";
          continue;
      }

      $product = ( $type === "variable" ) ? new WC_Product_Variable() : new WC_Product_Simple();
      $product->set_name( trim( $row["Name"] ) );
      $product->set_sku( $sku );
      $product->set_short_description( trim( $row["Short description"] ?? "" ) );
      $product->set_status( $row["Published"] === "1" ? "publish" : "draft" );
      $vis = trim( $row["Visibility in catalog"] ?? "" );
      $product->set_catalog_visibility( $vis ?: "visible" );
      $product->set_stock_status( $row["In stock?"] === "1" ? "instock" : "outofstock" );
      $price = trim( $row["Regular price"] ?? "" );
      if ( $price !== "" ) $product->set_regular_price( $price );

      // Categorías
      $cat_str = trim( $row["Categories"] ?? "" );
      if ( $cat_str ) {
          $cat_id = ensure_category( $cat_str );
          if ( $cat_id ) $product->set_category_ids( array( $cat_id ) );
      }

      // Atributos
      $product->set_attributes( build_attributes( $row, $type === "variable" ) );

      // Guardar para obtener ID
      $id = $product->save();
      if ( ! $id || is_wp_error( $id ) ) {
          echo "❌ Error guardando: $sku\n";
          $failed++;
          continue;
      }

      // Asignar términos de atributo al post (necesario para filtros en tienda)
      for ( $i = 1; $i <= 2; $i++ ) {
          $aname = trim( $row[ "Attribute $i name" ] ?? "" );
          $avals = trim( $row[ "Attribute $i value(s)" ] ?? "" );
          if ( ! $aname || ! $avals ) continue;
          $taxonomy = wc_attribute_taxonomy_name( $aname );
          $values   = array_filter( array_map( "trim", explode( ",", $avals ) ) );
          wp_set_object_terms( $id, $values, $taxonomy );
      }

      // Imagen
      $img_name = trim( $row["Images"] ?? "" );
      if ( $img_name && isset( $att_map[ $img_name ] ) ) {
          update_post_meta( $id, "_thumbnail_id", $att_map[ $img_name ] );
      } elseif ( $img_name ) {
          echo "⚠️  Imagen no encontrada: $img_name\n";
      }

      $sku_to_id[ $sku ] = $id;
      $imported++;
      echo "✅ $type creado (ID $id): $sku\n";
  }

  // ── PASO 2: variaciones ──────────────────────────────────────────────────────
  foreach ( $rows as $row ) {
      $type = strtolower( trim( $row["Type"] ?? "" ) );
      if ( $type !== "variation" ) continue;

      $sku        = trim( $row["SKU"] );
      $parent_sku = trim( $row["Parent"] );

      if ( wc_get_product_id_by_sku( $sku ) ) {
          echo "⏭️  Variación ya existe: $sku\n";
          continue;
      }

      $parent_id = $sku_to_id[ $parent_sku ] ?? wc_get_product_id_by_sku( $parent_sku );
      if ( ! $parent_id ) {
          echo "❌ Padre no encontrado para variación $sku (padre: $parent_sku)\n";
          $failed++;
          continue;
      }

      $variation = new WC_Product_Variation();
      $variation->set_parent_id( $parent_id );
      $variation->set_sku( $sku );
      $variation->set_status( "publish" );
      $variation->set_stock_status( $row["In stock?"] === "1" ? "instock" : "outofstock" );
      $price = trim( $row["Regular price"] ?? "" );
      if ( $price !== "" ) {
          $variation->set_regular_price( $price );
          $variation->set_price( $price );
      }

      // Imagen de variación
      $img_name = trim( $row["Images"] ?? "" );
      if ( $img_name && isset( $att_map[ $img_name ] ) ) {
          $variation->set_image_id( $att_map[ $img_name ] );
      }

      // Atributos de variación: WC espera el SLUG del término, no el nombre
      $var_attrs = array();
      for ( $i = 1; $i <= 2; $i++ ) {
          $aname = trim( $row[ "Attribute $i name" ] ?? "" );
          $aval  = trim( $row[ "Attribute $i value(s)" ] ?? "" );
          if ( ! $aname || ! $aval ) continue;
          $taxonomy = wc_attribute_taxonomy_name( $aname );
          // Obtener el slug real del término (WC guarda el slug, no el nombre)
          $term = get_term_by( "name", $aval, $taxonomy );
          $slug = $term ? $term->slug : sanitize_title( $aval );
          $var_attrs[ $taxonomy ] = $slug;
      }
      $variation->set_attributes( $var_attrs );

      $id = $variation->save();
      if ( ! $id || is_wp_error( $id ) ) {
          echo "❌ Error creando variación: $sku\n";
          $failed++;
          continue;
      }

      $sku_to_id[ $sku ] = $id;
      $imported++;
      echo "✅ Variación creada (ID $id): $sku → padre ID $parent_id\n";

      // Sincronizar precios del producto variable padre
      WC_Product_Variable::sync( $parent_id );
  }

  echo "\n📊 Resumen:\n";
  echo "✅ Creados: $imported\n";
  echo "❌ Fallidos: $failed\n";
' --user=${WP_ADMIN_USER}

echo "🔄 Refrescando tablas y contadores..."
wp wc tool run regenerate_product_lookup_tables --user=${WP_ADMIN_USER}
wp wc tool run recount_terms --user=${WP_ADMIN_USER}
wp wc tool run clear_transients --user=${WP_ADMIN_USER}
wp transient delete --all

echo "✅ Catálogo procesado y visible."
