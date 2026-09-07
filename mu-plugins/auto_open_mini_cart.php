<?php
/**
 * Plugin Name: WooCommerce Auto Open Mini Cart Drawer (Astra Compatible)
 * Description: Abre automáticamente el mini carrito lateral al agregar un producto por AJAX, agrega botones +/- con actualización en tiempo real y corrige la maquetación.
 * Version: 1.1.1
 * Author: Ruru Lab
 */

if (!defined('ABSPATH')) {
    exit; // Evitar acceso directo
}

// =========================================================================
// 1. FILTRO PARA MODIFICAR LA FILA DE PRECIO, CONTROLES (+ / -) Y TÍTULO
// =========================================================================
if (!function_exists('ruru_mini_cart_item_quantity_custom')) {
    function ruru_mini_cart_item_quantity_custom($html, $cart_item, $cart_item_key)
    {
        // Se usa get_product_subtotal para reflejar el total acumulado por producto según la cantidad
        $product_subtotal = WC()->cart->get_product_subtotal($cart_item['data'], $cart_item['quantity']);
        $cart_qty = $cart_item['quantity'];

        $output = '<div class="mini-cart-qty-row">';
        $output .= '<div class="mini-cart-qty-pill" data-cart-key="' . esc_attr($cart_item_key) . '">';
        $output .= '<button type="button" class="qty-btn minus" aria-label="Disminuir">-</button>';
        $output .= '<input type="number" class="qty-input" value="' . esc_attr($cart_qty) . '" min="1" step="1" readonly />';
        $output .= '<button type="button" class="qty-btn plus" aria-label="Aumentar">+</button>';
        $output .= '</div>';
        $output .= '<span class="mini-cart-item-price">' . $product_subtotal . '</span>';
        $output .= '</div>';

        return $output;
    }
    add_filter('woocommerce_widget_cart_item_quantity', 'ruru_mini_cart_item_quantity_custom', 10, 3);
}

if (!function_exists('ruru_mini_cart_item_name_custom')) {
    function ruru_mini_cart_item_name_custom($name, $cart_item, $cart_item_key)
    {
        if (is_cart() || is_checkout()) {
            return $name;
        }

        return '<span class="mini-cart-item-title">' . $name . '</span>';
    }
    add_filter('woocommerce_cart_item_name', 'ruru_mini_cart_item_name_custom', 10, 3);
}

// =========================================================================
// 2. ENDPOINT AJAX PARA ACTUALIZAR CANTIDAD EN TIEMPO REAL
// =========================================================================
if (!function_exists('ruru_update_mini_cart_qty_ajax')) {
    function ruru_update_mini_cart_qty_ajax()
    {
        if (!isset($_POST['cart_item_key']) || !isset($_POST['qty'])) {
            wp_send_json_error(array('message' => 'Datos inválidos'));
        }

        $cart_item_key = sanitize_text_field(wp_unslash($_POST['cart_item_key']));
        $qty = intval($_POST['qty']);

        if ($qty <= 0) {
            WC()->cart->remove_cart_item($cart_item_key);
        } else {
            WC()->cart->set_quantity($cart_item_key, $qty, true);
        }

        // Recalcular totales del carrito explícitamente
        WC()->cart->calculate_totals();

        WC_AJAX::get_refreshed_fragments();
        wp_die();
    }
    add_action('wp_ajax_ruru_update_mini_cart_qty', 'ruru_update_mini_cart_qty_ajax');
    add_action('wp_ajax_nopriv_ruru_update_mini_cart_qty', 'ruru_update_mini_cart_qty_ajax');
}

// =========================================================================
// 3. ESTRUCTURA HTML DEL DRAWER Y OVERLAY EN EL FOOTER
// =========================================================================
if (!function_exists('ruru_mini_cart_drawer_html')) {
    function ruru_mini_cart_drawer_html()
    {
        if (!class_exists('WooCommerce')) {
            return;
        }
        ?>
        <!-- Overlay de fondo -->
        <div id="custom-cart-overlay" class="custom-cart-overlay"></div>

        <!-- Panel Drawer Carrito Lateral -->
        <div id="custom-cart-drawer" class="custom-cart-drawer" role="dialog" aria-label="Carrito de compras">
            <div class="cart-drawer-header">
                <h3>Carrito de compra</h3>
                <button type="button" class="cart-drawer-close" aria-label="Cerrar carrito">&times;</button>
            </div>
            <div class="cart-drawer-body">
                <div class="widget_shopping_cart_content">
                    <?php if (function_exists('woocommerce_mini_cart')) {
                        woocommerce_mini_cart();
                    } ?>
                </div>
            </div>
        </div>
        <?php
    }
    add_action('wp_footer', 'ruru_mini_cart_drawer_html');
}

// =========================================================================
// 4. SCRIPTS JAVASCRIPT (APERTURA Y ACTUALIZACIÓN AJAX)
// =========================================================================
if (!function_exists('ruru_mini_cart_drawer_scripts')) {
    function ruru_mini_cart_drawer_scripts()
    {
        if (!class_exists('WooCommerce')) {
            return;
        }
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                var ajaxUrl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";

                function openCartDrawer() {
                    // Abrir drawer personalizado
                    $('#custom-cart-drawer, #custom-mini-cart-drawer, #custom-cart-overlay').addClass('is-open');
                    $('body').addClass('custom-cart-drawer-open');

                    // Abrir drawer nativo de Astra (Desktop / Móvil)
                    $('#astra-mobile-cart-drawer, .astra-cart-drawer').addClass('active');
                    $('.astra-mobile-cart-overlay, .astra-cart-drawer-overlay').addClass('active');
                    $('body, html').addClass('ast-cart-drawer-active ast-mobile-cart-active');
                    $(document.body).trigger('astra_open_cart_drawer');
                }

                function closeCartDrawer() {
                    // Cerrar drawer personalizado
                    $('#custom-cart-drawer, #custom-mini-cart-drawer, #custom-cart-overlay').removeClass('is-open');
                    $('body').removeClass('custom-cart-drawer-open');

                    // Cerrar drawer nativo de Astra
                    $('#astra-mobile-cart-drawer, .astra-cart-drawer').removeClass('active');
                    $('.astra-mobile-cart-overlay, .astra-cart-drawer-overlay').removeClass('active');
                    $('body, html').removeClass('ast-cart-drawer-active ast-mobile-cart-active');
                }

                // Eventos de cierre
                $(document).on('click', '.cart-drawer-close, .astra-cart-drawer-close, #custom-cart-overlay, .astra-mobile-cart-overlay, .astra-cart-drawer-overlay', function (e) {
                    e.preventDefault();
                    closeCartDrawer();
                });

                // Apertura al agregar producto por AJAX
                $(document.body).on('added_to_cart', function (event, fragments, cart_hash, $button) {
                    openCartDrawer();
                });

                // Controladores de botones + y - en el mini carrito (Compatible con Custom Drawer y Astra Drawer)
                $(document).on('click', '.mini-cart-qty-pill .qty-btn', function (e) {
                    e.preventDefault();
                    var $btn = $(this);
                    var $pill = $btn.closest('.mini-cart-qty-pill');

                    if ($pill.hasClass('loading')) {
                        return;
                    }

                    var $input = $pill.find('.qty-input');
                    var cartKey = $pill.data('cart-key');
                    var currentQty = parseInt($input.val()) || 1;
                    var newQty = currentQty;

                    if ($btn.hasClass('plus')) {
                        newQty += 1;
                    } else if ($btn.hasClass('minus')) {
                        newQty -= 1;
                    }

                    $pill.addClass('loading');
                    $pill.closest('.mini_cart_item').css('opacity', '0.6');

                    $.ajax({
                        type: 'POST',
                        url: ajaxUrl,
                        data: {
                            action: 'ruru_update_mini_cart_qty',
                            cart_item_key: cartKey,
                            qty: newQty
                        },
                        success: function (response) {
                            if (response && response.fragments) {
                                $.each(response.fragments, function (key, value) {
                                    $(key).replaceWith(value);
                                });
                                $(document.body).trigger('wc_fragments_refreshed');
                                $(document.body).trigger('wc_fragments_loaded');
                                $(document.body).trigger('updated_cart_totals');
                            }
                        },
                        complete: function () {
                            $pill.removeClass('loading');
                        }
                    });
                });
            });
        </script>
        <?php
    }
    add_action('wp_footer', 'ruru_mini_cart_drawer_scripts');
}

// =========================================================================
// 5. ACTUALIZACIÓN DE FRAGMENTOS WOOCOMMERCE
// =========================================================================
if (!function_exists('ruru_mini_cart_drawer_fragments')) {
    function ruru_mini_cart_drawer_fragments($fragments)
    {
        ob_start();
        ?>
        <div class="widget_shopping_cart_content">
            <?php if (function_exists('woocommerce_mini_cart')) {
                woocommerce_mini_cart();
            } ?>
        </div>
        <?php
        $fragments['div.widget_shopping_cart_content'] = ob_get_clean();
        return $fragments;
    }
    add_filter('woocommerce_add_to_cart_fragments', 'ruru_mini_cart_drawer_fragments');
}

// =========================================================================
// 6. ESTILOS CSS
// =========================================================================
if (!function_exists('ruru_mini_cart_drawer_styles')) {
    function ruru_mini_cart_drawer_styles()
    {
        if (!class_exists('WooCommerce')) {
            return;
        }
        ?>
        <style type="text/css">
            /* ================================================================
                     * Overlay
                     * ================================================================ */
            .custom-cart-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.4);
                z-index: 999998;
                display: none;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .custom-cart-overlay.is-open {
                display: block;
                opacity: 1;
            }

            /* ================================================================
                     * Panel Lateral Drawer
                     * ================================================================ */
            .custom-cart-drawer,
            #custom-cart-drawer,
            #custom-mini-cart-drawer {
                position: fixed !important;
                top: 0 !important;
                right: -420px !important;
                width: 400px !important;
                max-width: 90vw !important;
                height: 100vh !important;
                background: #ffffff !important;
                box-shadow: -5px 0 25px rgba(0, 0, 0, 0.15) !important;
                z-index: 999999 !important;
                transition: right 0.35s cubic-bezier(0.25, 0.8, 0.25, 1) !important;
                display: flex !important;
                flex-direction: column !important;
                box-sizing: border-box !important;
            }

            .custom-cart-drawer.is-open,
            #custom-cart-drawer.is-open,
            #custom-mini-cart-drawer.is-open {
                right: 0 !important;
            }

            /* ================================================================
                     * Header
                     * ================================================================ */
            .cart-drawer-header,
            .astra-cart-drawer-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 18px 22px;
                border-bottom: 1px solid #eee;
                background: #fff;
            }

            .cart-drawer-header h3,
            .astra-cart-drawer-title {
                margin: 0;
                font-size: 18px;
                color: #111;
                font-weight: 700;
            }

            .cart-drawer-close,
            .astra-cart-drawer-close {
                background: none;
                border: none;
                font-size: 26px;
                cursor: pointer;
                color: #666;
                line-height: 1;
            }

            /* ================================================================
                     * Body / Contenedor
                     * ================================================================ */
            .cart-drawer-body,
            .astra-cart-drawer-content {
                padding: 20px;
                flex: 1;
                overflow-y: auto;
            }

            .custom-cart-drawer ul.woocommerce-mini-cart,
            #custom-mini-cart-drawer ul.woocommerce-mini-cart,
            .astra-cart-drawer ul.woocommerce-mini-cart,
            #astra-mobile-cart-drawer ul.woocommerce-mini-cart,
            .widget_shopping_cart ul.woocommerce-mini-cart {
                list-style: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* ================================================================
                     * Ítem individual
                     *
                     * Ajuste principal:
                     * - La imagen queda centrada verticalmente con align-self:center.
                     * - La segunda columna usa minmax(0,1fr) para evitar desbordes.
                     * - align-items:center evita que el contenido quede pegado arriba.
                     * ================================================================ */
            .custom-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item,
            #custom-mini-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item,
            .astra-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item,
            #astra-mobile-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item,
            .widget_shopping_cart ul.woocommerce-mini-cart li.mini_cart_item {
                position: relative !important;
                display: grid !important;
                grid-template-columns: 80px minmax(0, 1fr) !important;
                grid-template-rows: auto auto !important;
                column-gap: 14px !important;
                row-gap: 8px !important;
                align-items: center !important;
                padding: 16px 28px 16px 0 !important;
                border-bottom: 1px solid #f2f2f2 !important;
                margin: 0 !important;
                float: none !important;
                transition: opacity 0.2s ease;
                box-sizing: border-box !important;
            }

            /* ================================================================
                     * Botón eliminar (X)
                     * ================================================================ */
            .custom-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item a.remove,
            #custom-mini-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item a.remove,
            .astra-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item a.remove,
            #astra-mobile-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item a.remove,
            .widget_shopping_cart ul.woocommerce-mini-cart li.mini_cart_item a.remove {
                position: absolute !important;
                top: 14px !important;
                right: 0 !important;
                width: 22px !important;
                height: 22px !important;
                padding: 0 !important;
                margin: 0 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                color: #bbb !important;
                background: none !important;
                float: none !important;
                text-decoration: none !important;
                z-index: 2 !important;
            }

            .custom-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item a.remove:hover,
            #custom-mini-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item a.remove:hover,
            .astra-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item a.remove:hover,
            #astra-mobile-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item a.remove:hover,
            .widget_shopping_cart ul.woocommerce-mini-cart li.mini_cart_item a.remove:hover {
                color: #e53935 !important;
            }

            .custom-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item a.remove svg,
            #custom-mini-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item a.remove svg,
            .astra-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item a.remove svg,
            #astra-mobile-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item a.remove svg,
            .widget_shopping_cart ul.woocommerce-mini-cart li.mini_cart_item a.remove svg {
                width: 16px !important;
                height: 16px !important;
            }

            /* ================================================================
                     * Enlace del producto
                     * ================================================================ */
            .custom-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item>a:not(.remove),
            #custom-mini-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item>a:not(.remove),
            .astra-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item>a:not(.remove),
            #astra-mobile-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item>a:not(.remove),
            .widget_shopping_cart ul.woocommerce-mini-cart li.mini_cart_item>a:not(.remove) {
                display: contents !important;
                text-decoration: none !important;
            }

            /* ================================================================
                     * Imagen del producto
                     * ================================================================ */
            .custom-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item img,
            .custom-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .woocommerce-placeholder,
            #custom-mini-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item img,
            #custom-mini-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .woocommerce-placeholder,
            .astra-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item img,
            .astra-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .woocommerce-placeholder,
            #astra-mobile-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item img,
            #astra-mobile-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .woocommerce-placeholder,
            .widget_shopping_cart ul.woocommerce-mini-cart li.mini_cart_item img,
            .widget_shopping_cart ul.woocommerce-mini-cart li.mini_cart_item .woocommerce-placeholder {
                grid-column: 1 !important;
                grid-row: 1 / span 2 !important;
                align-self: center !important;
                justify-self: start !important;
                width: 68px !important;
                height: 68px !important;
                min-width: 68px !important;
                max-width: 68px !important;
                aspect-ratio: 1 / 1 !important;
                object-fit: contain !important;
                object-position: center !important;
                border-radius: 8px !important;
                border: 1px solid #ebebeb !important;
                background: #ffffff !important;
                padding: 4px !important;
                margin: 0 !important;
                box-sizing: border-box !important;
                display: block !important;
                float: none !important;
            }

            /* ================================================================
                     * Título del Producto
                     * ================================================================ */
            .custom-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .mini-cart-item-title,
            #custom-mini-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .mini-cart-item-title,
            .astra-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .mini-cart-item-title,
            #astra-mobile-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .mini-cart-item-title,
            .widget_shopping_cart ul.woocommerce-mini-cart li.mini_cart_item .mini-cart-item-title {
                grid-column: 2 !important;
                grid-row: 1 !important;
                align-self: end !important;
                display: block !important;
                min-width: 0 !important;
                font-size: 13px !important;
                font-weight: 600 !important;
                color: #111 !important;
                line-height: 1.35 !important;
                padding-right: 14px !important;
                margin: 0 !important;
                box-sizing: border-box !important;
            }

            /* Evitar que el nombre de producto se desborde */
            .custom-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .mini-cart-item-title a,
            #custom-mini-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .mini-cart-item-title a,
            .astra-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .mini-cart-item-title a,
            #astra-mobile-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .mini-cart-item-title a,
            .widget_shopping_cart ul.woocommerce-mini-cart li.mini_cart_item .mini-cart-item-title a {
                display: inline !important;
                font-size: inherit !important;
                font-weight: inherit !important;
                line-height: inherit !important;
                color: inherit !important;
                text-decoration: none !important;
            }

            /* ================================================================
                     * Fila de Cantidad y Subtotal
                     * ================================================================ */
            .custom-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .mini-cart-qty-row,
            #custom-mini-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .mini-cart-qty-row,
            .astra-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .mini-cart-qty-row,
            #astra-mobile-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .mini-cart-qty-row,
            .widget_shopping_cart ul.woocommerce-mini-cart li.mini_cart_item .mini-cart-qty-row {
                grid-column: 2 !important;
                grid-row: 2 !important;
                width: 100% !important;
                min-width: 0 !important;
                margin: 4px 0 0 0 !important;
                padding: 0 !important;
                box-sizing: border-box !important;
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 8px !important;
            }

            .mini-cart-item-price {
                font-size: 14px;
                font-weight: 700;
                color: #222;
                white-space: nowrap;
                flex-shrink: 0;
                line-height: 1.2;
            }

            /* ================================================================
                     * Control Píldora (- 1 +)
                     * ================================================================ */
            .mini-cart-qty-pill {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 1px solid #dcdcdc;
                border-radius: 20px;
                padding: 2px 8px;
                background: #fff;
                gap: 4px;
                min-height: 32px;
                box-sizing: border-box;
                flex-shrink: 0;
            }

            .mini-cart-qty-pill.loading {
                opacity: 0.4;
                pointer-events: none;
            }

            .mini-cart-qty-pill .qty-btn {
                background: none;
                border: none;
                font-size: 15px;
                font-weight: 700;
                color: #444;
                cursor: pointer;
                padding: 0 6px;
                line-height: 1;
                user-select: none;
                height: 24px;
                min-width: 24px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .mini-cart-qty-pill .qty-btn:hover {
                color: #8cc63f;
            }

            .mini-cart-qty-pill .qty-input {
                width: 24px;
                min-width: 24px;
                border: none;
                outline: none;
                box-shadow: none;
                background: transparent;
                text-align: center;
                font-size: 13px;
                font-weight: 700;
                color: #111;
                padding: 0;
                margin: 0;
                -moz-appearance: textfield;
            }

            .mini-cart-qty-pill .qty-input::-webkit-outer-spin-button,
            .mini-cart-qty-pill .qty-input::-webkit-inner-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }

            /* ================================================================
                     * Subtotal y Botones
                     * ================================================================ */
            .custom-cart-drawer .woocommerce-mini-cart__total,
            .astra-cart-drawer .woocommerce-mini-cart__total,
            .widget_shopping_cart .woocommerce-mini-cart__total {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 20px;
                padding-top: 16px;
                border-top: 2px solid #eee;
                font-size: 16px;
                font-weight: 700;
                color: #111;
            }

            .custom-cart-drawer .woocommerce-mini-cart__buttons,
            .astra-cart-drawer .woocommerce-mini-cart__buttons,
            .widget_shopping_cart .woocommerce-mini-cart__buttons {
                display: flex;
                flex-direction: column;
                gap: 10px;
                margin-top: 16px;
            }

            .custom-cart-drawer .woocommerce-mini-cart__buttons a.button,
            .astra-cart-drawer .woocommerce-mini-cart__buttons a.button,
            .widget_shopping_cart .woocommerce-mini-cart__buttons a.button {
                display: block;
                text-align: center;
                padding: 13px 20px;
                border-radius: 25px;
                font-weight: 700;
                text-decoration: none;
                font-size: 14px;
                text-transform: uppercase;
            }

            .custom-cart-drawer .woocommerce-mini-cart__buttons a.button:not(.checkout),
            .astra-cart-drawer .woocommerce-mini-cart__buttons a.button:not(.checkout),
            .widget_shopping_cart .woocommerce-mini-cart__buttons a.button:not(.checkout) {
                background-color: #8cc63f !important;
                color: #fff !important;
            }

            .custom-cart-drawer .woocommerce-mini-cart__buttons a.checkout,
            .astra-cart-drawer .woocommerce-mini-cart__buttons a.checkout,
            .widget_shopping_cart .woocommerce-mini-cart__buttons a.checkout {
                background-color: #7cb342 !important;
                color: #fff !important;
            }

            /* ================================================================
                     * Responsive: pantallas pequeñas
                     * ================================================================ */
            @media (max-width: 480px) {

                .custom-cart-drawer,
                #custom-cart-drawer,
                #custom-mini-cart-drawer {
                    width: 100vw !important;
                    max-width: 100vw !important;
                }

                .cart-drawer-body,
                .astra-cart-drawer-content {
                    padding: 16px;
                }

                .custom-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item,
                #custom-mini-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item,
                .astra-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item,
                #astra-mobile-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item,
                .widget_shopping_cart ul.woocommerce-mini-cart li.mini_cart_item {
                    grid-template-columns: 72px minmax(0, 1fr) !important;
                    column-gap: 12px !important;
                    padding-right: 24px !important;
                }

                .custom-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item img,
                .custom-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .woocommerce-placeholder,
                #custom-mini-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item img,
                #custom-mini-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .woocommerce-placeholder,
                .astra-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item img,
                .astra-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .woocommerce-placeholder,
                #astra-mobile-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item img,
                #astra-mobile-cart-drawer ul.woocommerce-mini-cart li.mini_cart_item .woocommerce-placeholder,
                .widget_shopping_cart ul.woocommerce-mini-cart li.mini_cart_item img,
                .widget_shopping_cart ul.woocommerce-mini-cart li.mini_cart_item .woocommerce-placeholder {
                    width: 64px !important;
                    height: 64px !important;
                    min-width: 64px !important;
                    max-width: 64px !important;
                }

                .mini-cart-item-price {
                    font-size: 13px;
                }
            }
        </style>
        <?php
    }
    add_action('wp_head', 'ruru_mini_cart_drawer_styles');
}
