<?php
/**
 * Plugin Name:  Excluir Categorías del Envío Gratis
 * Description:  Intercepta las tarifas generadas por el plugin "Fix Envíos - Integración CSV"
 *               (o "Envío Personalizado por Zonas") y recalcula si el carrito califica para
 *               envío gratis excluyendo los productos de las categorías "nutricion-adultos"
 *               y "nutricion-ninos" / "nutricion-infantil".
 * Version:      3.1
 * Author:       kaox
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CONFIGURACIÓN CENTRALIZADA
 */
define( 'ECEG_CATEGORIAS_EXCLUIDAS', array(
    // Slugs estándar
    'nutricion-adultos',
    'nutricion-adulto',
    'nutricion-ninos',
    'nutricion-nino',
    'nutricion-infantil',
    // Nombres legibles con/sin tilde
    'Nutrición Adultos',
    'Nutricion Adultos',
    'Nutrición Niños',
    'Nutricion Ninos',
    'Nutrición Infantil',
    'Nutricion Infantil',
) );

define( 'ECEG_OPTION_TARIFAS', 'wc_custom_shipping_rates' ); // Opción del importador CSV
define( 'ECEG_DEBUG',          true );                        // Log activo en error_log()

// ---------------------------------------------------------------------------
// FILTRO PRINCIPAL (Prioridad 999 para ejecutarse después de ubigeo-shipping-fix a prioridad 99)
// ---------------------------------------------------------------------------
add_filter( 'woocommerce_package_rates', 'eceg_recalcular_envio_gratis', 999, 2 );

// Forzar recalculación evitando tarifas cacheadas en la sesión durante el checkout
add_action( 'woocommerce_checkout_update_order_review', function() {
    if ( function_exists( 'WC' ) && WC()->session ) {
        WC()->session->set( 'shipping_for_package_0', null );
    }
} );

/**
 * Recalcula si el paquete califica para envío gratis.
 *
 * @param WC_Shipping_Rate[] $rates   Tarifas calculadas.
 * @param array              $package Paquete de envío actual.
 * @return WC_Shipping_Rate[]
 */
function eceg_recalcular_envio_gratis( $rates, $package ) {

    // --- 1. Calcular subtotal elegible (excluyendo categorías configuradas) ---
    $subtotal_elegible         = 0;
    $tiene_productos_excluidos = false;

    foreach ( $package['contents'] as $cart_item ) {
        if ( eceg_item_es_excluido( $cart_item ) ) {
            $tiene_productos_excluidos = true;
            $nombre_item = isset( $cart_item['data'] ) && is_a( $cart_item['data'], 'WC_Product' )
                ? $cart_item['data']->get_name()
                : ( 'ID: ' . ( $cart_item['product_id'] ?? '' ) );

            eceg_log( sprintf(
                'Producto excluido detectado: "%s" | Subtotal ítem omitido: %s',
                $nombre_item,
                $cart_item['line_subtotal']
            ) );
            continue; // No suma al subtotal elegible para envío gratis
        }

        $subtotal_elegible += (float) $cart_item['line_subtotal'];
    }

    // Si NO hay productos excluidos en el carrito, no hay nada que ajustar
    if ( ! $tiene_productos_excluidos ) {
        eceg_log( 'No hay productos de nutrición en el carrito — tarifas intactas.' );
        return $rates;
    }

    eceg_log( sprintf( 'Subtotal elegible para envío gratis (sin nutrición): S/ %0.2f', $subtotal_elegible ) );

    // --- 2. Consultar tarifas del CSV ---
    $custom_rates = get_option( ECEG_OPTION_TARIFAS, array() );
    if ( empty( $custom_rates ) ) {
        eceg_log( 'No se encontraron tarifas en la opción "' . ECEG_OPTION_TARIFAS . '".' );
        return $rates;
    }

    // --- 3. Identificar el distrito destino real ---
    $info_distrito = eceg_buscar_tarifa_distrito( $package, $custom_rates );

    if ( ! $info_distrito ) {
        eceg_log( 'No se pudo mapear el distrito a una tarifa del CSV.' );
        return $rates;
    }

    $distrito            = $info_distrito['distrito'];
    $monto_minimo_gratis = floatval( $info_distrito['tarifa']['gratis'] );
    $costo_regular       = floatval( $info_distrito['tarifa']['costo'] );

    eceg_log( sprintf(
        'Distrito identificado: "%s" | Mínimo Gratis: S/ %0.2f | Costo regular: S/ %0.2f',
        $distrito,
        $monto_minimo_gratis,
        $costo_regular
    ) );

    // --- 4. Si el subtotal elegible alcanza el monto mínimo, se mantiene el envío gratis ---
    if ( $monto_minimo_gratis > 0 && $subtotal_elegible >= $monto_minimo_gratis ) {
        eceg_log( sprintf(
            'El subtotal elegible (S/ %0.2f) alcanza el mínimo (S/ %0.2f) → Mantiene envío gratis.',
            $subtotal_elegible,
            $monto_minimo_gratis
        ) );
        return $rates;
    }

    // --- 5. El subtotal elegible NO alcanza el mínimo → Revertir tarifa gratis a costo regular ---
    eceg_log( sprintf(
        'El subtotal elegible (S/ %0.2f) NO alcanza el mínimo (S/ %0.2f) → Revirtiendo tarifa gratuita.',
        $subtotal_elegible,
        $monto_minimo_gratis
    ) );

    $nombre_distrito_legible = ucwords( mb_strtolower( $distrito, 'UTF-8' ) );

    foreach ( $rates as $rate_key => $rate ) {
        // No tocar recogida local
        if ( strpos( $rate->method_id, 'local_pickup' ) !== false ) {
            continue;
        }

        // Si la tarifa es gratuita (costo 0 o dice "Gratis" en el título)
        $es_gratis = ( (float) $rate->cost === 0.0 ) || ( stripos( $rate->label, 'Gratis' ) !== false );

        if ( $es_gratis ) {
            eceg_log( sprintf(
                'Revirtiendo tarifa "%s" (costo previo: %s) a costo regular S/ %0.2f',
                $rate->label,
                $rate->cost,
                $costo_regular
            ) );

            $rates[ $rate_key ]->cost  = $costo_regular;
            $rates[ $rate_key ]->label = 'Envío a ' . $nombre_distrito_legible;

            // Recalcular impuestos de envío si aplica
            if ( class_exists( 'WC_Tax' ) ) {
                $rates[ $rate_key ]->taxes = WC_Tax::calc_shipping_tax(
                    $costo_regular,
                    WC_Tax::get_shipping_tax_rates()
                );
            }
        }
    }

    return $rates;
}

// ---------------------------------------------------------------------------
// HELPERS
// ---------------------------------------------------------------------------

/**
 * Comprueba si un ítem del carrito pertenece a las categorías excluidas.
 * Soporta slugs, nombres, productos variables y términos jerárquicos.
 *
 * @param array $cart_item
 * @return bool
 */
function eceg_item_es_excluido( $cart_item ) {
    $product_id   = ! empty( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
    $variation_id = ! empty( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : 0;

    // 1. Verificación directa con has_term usando la lista configurada
    if ( $product_id && has_term( ECEG_CATEGORIAS_EXCLUIDAS, 'product_cat', $product_id ) ) {
        return true;
    }
    if ( $variation_id && has_term( ECEG_CATEGORIAS_EXCLUIDAS, 'product_cat', $variation_id ) ) {
        return true;
    }

    // 2. Inspección de términos y jerarquías (ancestros)
    $ids_a_inspeccionar = array_filter( array( $product_id, $variation_id ) );
    if ( isset( $cart_item['data'] ) && is_a( $cart_item['data'], 'WC_Product' ) ) {
        $ids_a_inspeccionar[] = $cart_item['data']->get_id();
        $parent = $cart_item['data']->get_parent_id();
        if ( $parent ) {
            $ids_a_inspeccionar[] = $parent;
        }
    }
    $ids_a_inspeccionar = array_unique( $ids_a_inspeccionar );

    foreach ( $ids_a_inspeccionar as $id ) {
        $terms = wp_get_post_terms( $id, 'product_cat' );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            continue;
        }

        foreach ( $terms as $term ) {
            if ( eceg_termino_coincide( $term ) ) {
                return true;
            }

            // Chequear ancestros/padres
            $ancestors = get_ancestors( $term->term_id, 'product_cat' );
            foreach ( $ancestors as $ancestor_id ) {
                $ancestor = get_term( $ancestor_id, 'product_cat' );
                if ( $ancestor && ! is_wp_error( $ancestor ) && eceg_termino_coincide( $ancestor ) ) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Comprueba si un término individual corresponde a nutrición infantil/adultos.
 *
 * @param WP_Term $term
 * @return bool
 */
function eceg_termino_coincide( $term ) {
    $slug = strtolower( trim( $term->slug ) );
    $name = function_exists( 'remove_accents' )
        ? strtolower( trim( remove_accents( $term->name ) ) )
        : strtolower( trim( $term->name ) );

    // Coincidencia con slugs clave
    if ( strpos( $slug, 'nutricion-adult' ) !== false ||
         strpos( $slug, 'nutricion-nin' ) !== false ||
         strpos( $slug, 'nutricion-infant' ) !== false ) {
        return true;
    }

    // Coincidencia con nombres clave
    if ( strpos( $name, 'nutricion' ) !== false ) {
        if ( strpos( $name, 'adult' ) !== false ||
             strpos( $name, 'nin' ) !== false ||
             strpos( $name, 'infant' ) !== false ) {
            return true;
        }
    }

    return false;
}

/**
 * Encuentra el distrito en los datos del paquete o request y obtiene su tarifa del CSV.
 *
 * @param array $package
 * @param array $custom_rates
 * @return array|null Array con 'distrito' y 'tarifa', o null si no se encuentra.
 */
function eceg_buscar_tarifa_distrito( $package, $custom_rates ) {
    $candidatos = array();

    // 1. Prioridad: 'distrito' inyectado por ubigeo-shipping-fix.php
    if ( ! empty( $package['destination']['distrito'] ) ) {
        $candidatos[] = $package['destination']['distrito'];
    }

    // 2. Variables directas en $_POST (AJAX de WooCommerce)
    if ( ! empty( $_POST['real_distrito'] ) ) {
        $candidatos[] = wp_unslash( $_POST['real_distrito'] );
    }
    if ( ! empty( $_POST['billing_distrito'] ) ) {
        $candidatos[] = wp_unslash( $_POST['billing_distrito'] );
    }
    if ( ! empty( $_POST['shipping_distrito'] ) ) {
        $candidatos[] = wp_unslash( $_POST['shipping_distrito'] );
    }

    // 3. Dentro del post_data serializado
    if ( isset( $_POST['post_data'] ) ) {
        parse_str( $_POST['post_data'], $post_data );
        if ( ! empty( $post_data['real_distrito'] ) ) {
            $candidatos[] = $post_data['real_distrito'];
        }
        if ( ! empty( $post_data['billing_distrito'] ) ) {
            $candidatos[] = $post_data['billing_distrito'];
        }
        if ( ! empty( $post_data['shipping_distrito'] ) ) {
            $candidatos[] = $post_data['shipping_distrito'];
        }
    }

    // 4. Fallback address_2
    if ( ! empty( $package['destination']['address_2'] ) ) {
        $candidatos[] = $package['destination']['address_2'];
    }

    // 5. Fallback city (solo si no es la provincia general "LIMA")
    if ( ! empty( $package['destination']['city'] ) ) {
        $candidatos[] = $package['destination']['city'];
    }

    // Buscar el primer candidato que coincida con el CSV
    foreach ( $candidatos as $cand ) {
        $cand_clean = mb_strtoupper( trim( $cand ), 'UTF-8' );
        if ( empty( $cand_clean ) || $cand_clean === 'SELECCIONE' || ( $cand_clean === 'LIMA' && ! isset( $custom_rates['LIMA'] ) ) ) {
            continue;
        }

        // Búsqueda exacta
        if ( isset( $custom_rates[ $cand_clean ] ) ) {
            return array(
                'distrito' => $cand_clean,
                'tarifa'   => $custom_rates[ $cand_clean ],
            );
        }

        // Búsqueda tolerante a tildes (ej: BREÑA vs BRENA)
        $cand_ascii = function_exists( 'remove_accents' ) ? remove_accents( $cand_clean ) : $cand_clean;
        foreach ( $custom_rates as $distrito_key => $tarifa_data ) {
            $key_ascii = function_exists( 'remove_accents' )
                ? remove_accents( mb_strtoupper( $distrito_key, 'UTF-8' ) )
                : mb_strtoupper( $distrito_key, 'UTF-8' );

            if ( $key_ascii === $cand_ascii ) {
                return array(
                    'distrito' => $distrito_key,
                    'tarifa'   => $tarifa_data,
                );
            }
        }
    }

    return null;
}

/**
 * Función de logging de depuración.
 *
 * @param string $mensaje
 */
function eceg_log( $mensaje ) {
    if ( defined( 'ECEG_DEBUG' ) && ECEG_DEBUG ) {
        error_log( '[ECEG] ' . $mensaje );
    }
}