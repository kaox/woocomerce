<?php
/**
 * Plugin Name: WooCommerce Auto Open Mini Cart Drawer (Astra Compatible)
 * Description: Abre automáticamente el mini carrito lateral al agregar un producto por AJAX, agrega botones +/- con actualización en tiempo real y corrige la maquetación.
 * Version: 1.1.0
 * Author: Ruru Lab
 */

if (!defined('ABSPATH')) {
    exit; // Evitar acceso directo
}

// =========================================================================
// 1. FILTRO PARA MODIFICAR LA FILA DE PRECIO Y CONTROLES (+ / -)
// =========================================================================
if (!function_exists('ruru_mini_cart_item_quantity_custom')) {
    function ruru_mini_cart_item_quantity_custom($html, $cart_item, $cart_item_key)
    {
        $product_price = WC()->cart->get_product_price($cart_item['data']);
        $cart_qty = $cart_item['quantity'];

        $output = '<div class="mini-cart-qty-row">';
        $output .= '<div class="mini-cart-qty-pill" data-cart-key="' . esc_attr($cart_item_key) . '">';
        $output .= '<button type="button" class="qty-btn minus" aria-label="Disminuir">-</button>';
        $output .= '<input type="number" class="qty-input" value="' . esc_attr($cart_qty) . '" min="1" step="1" readonly />';
        $output .= '<button type="button" class="qty-btn plus" aria-label="Aumentar">+</button>';
        $output .= '</div>';
        $output .= '<span class="mini-cart-item-price">' . $product_price . '</span>';
        $output .= '</div>';

        return $output;
    }
    add_filter('woocommerce_widget_cart_item_quantity', 'ruru_mini_cart_item_quantity_custom', 10, 3);
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

        $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
        $qty = intval($_POST['qty']);

        if ($qty <= 0) {
            WC()->cart->remove_cart_item($cart_item_key);
        } else {
            WC()->cart->set_quantity($cart_item_key, $qty, true);
        }

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
        if (!class_exists('WooCommerce'))
            return;
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
        if (!class_exists('WooCommerce'))
            return;
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                var ajaxUrl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";

                function openCartDrawer() {
                    $('#custom-cart-drawer, #custom-cart-overlay').addClass('is-open');
                    $('body').addClass('custom-cart-drawer-open');
                }

                function closeCartDrawer() {
                    $('#custom-cart-drawer, #custom-cart-overlay').removeClass('is-open');
                    $('body').removeClass('custom-cart-drawer-open');
                }

                // Eventos de cierre
                $(document).on('click', '.cart-drawer-close, #custom-cart-overlay', function (e) {
                    e.preventDefault();
                    closeCartDrawer();
                });

                // Apertura al agregar producto por AJAX
                $(document.body).on('added_to_cart', function (event, fragments, cart_hash, $button) {
                    openCartDrawer();
                });

                // Controladores de botones + y - en el mini carrito
                $(document).on('click', '.mini-cart-qty-pill .qty-btn', function (e) {
                    e.preventDefault();
                    var $btn = $(this);
                    var $pill = $btn.closest('.mini-cart-qty-pill');
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
        if (!class_exists('WooCommerce'))
            return;
        ?>
        <style type="text/css">
            /* Overlay */
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

            /* Panel Lateral Drawer */
            .custom-cart-drawer {
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

            .custom-cart-drawer.is-open {
                right: 0 !important;
            }

            /* Header */
            .cart-drawer-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 18px 22px;
                border-bottom: 1px solid #eee;
                background: #fff;
            }

            .cart-drawer-header h3 {
                margin: 0;
                font-size: 18px;
                color: #111;
                font-weight: 700;
            }

            .cart-drawer-close {
                background: none;
                border: none;
                font-size: 26px;
                cursor: pointer;
                color: #666;
                line-height: 1;
            }

            /* Body */
            .cart-drawer-body {
                padding: 20px;
                flex: 1;
                overflow-y: auto;
            }

            .cart-drawer-body ul.woocommerce-mini-cart {
                list-style: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* Ítem individual */
            .cart-drawer-body ul.woocommerce-mini-cart li.mini_cart_item {
                position: relative !important;
                display: flex !important;
                flex-wrap: wrap !important;
                padding: 16px 28px 16px 0 !important;
                border-bottom: 1px solid #f2f2f2 !important;
                margin: 0 !important;
                float: none !important;
            }

            /* Botón eliminar (X) */
            .cart-drawer-body ul.woocommerce-mini-cart li.mini_cart_item a.remove {
                position: absolute !important;
                top: 16px !important;
                right: 0 !important;
                width: 20px !important;
                height: 20px !important;
                padding: 0 !important;
                margin: 0 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                color: #ccc !important;
                background: none !important;
                float: none !important;
            }

            .cart-drawer-body ul.woocommerce-mini-cart li.mini_cart_item a.remove:hover {
                color: #e53935 !important;
            }

            /* Título e Imagen */
            .cart-drawer-body ul.woocommerce-mini-cart li.mini_cart_item>a:not(.remove) {
                display: flex !important;
                align-items: center !important;
                gap: 14px !important;
                width: 100% !important;
                float: none !important;
                text-decoration: none !important;
                color: #111 !important;
                font-weight: 600 !important;
                font-size: 13px !important;
                line-height: 1.3 !important;
            }

            .cart-drawer-body ul.woocommerce-mini-cart li.mini_cart_item>a:not(.remove) img {
                float: none !important;
                display: block !important;
                width: 60px !important;
                height: 60px !important;
                min-width: 60px !important;
                object-fit: contain !important;
                border-radius: 8px !important;
                background: #f9f9f9 !important;
                padding: 4px !important;
                margin: 0 !important;
                flex-shrink: 0 !important;
            }

            /* Fila de Cantidad (- 1 +) y Precio */
            .cart-drawer-body ul.woocommerce-mini-cart li.mini_cart_item .mini-cart-qty-row {
                width: 100% !important;
                margin-top: 10px !important;
                padding-left: 74px !important;
                box-sizing: border-box !important;
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
            }

            .mini-cart-item-price {
                font-size: 14px;
                font-weight: 700;
                color: #222;
            }

            /* Control Píldora (- 1 +) */
            .mini-cart-qty-pill {
                display: inline-flex;
                align-items: center;
                border: 1px solid #dcdcdc;
                border-radius: 20px;
                padding: 2px 8px;
                background: #fff;
                gap: 4px;
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
            }

            .mini-cart-qty-pill .qty-btn:hover {
                color: #8cc63f;
            }

            .mini-cart-qty-pill .qty-input {
                width: 24px;
                border: none;
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

            /* Subtotal y Botones */
            .cart-drawer-body .woocommerce-mini-cart__total {
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

            .cart-drawer-body .woocommerce-mini-cart__buttons {
                display: flex;
                flex-direction: column;
                gap: 10px;
                margin-top: 16px;
            }

            .cart-drawer-body .woocommerce-mini-cart__buttons a.button {
                display: block;
                text-align: center;
                padding: 13px 20px;
                border-radius: 25px;
                font-weight: 700;
                text-decoration: none;
                font-size: 14px;
                text-transform: uppercase;
            }

            .cart-drawer-body .woocommerce-mini-cart__buttons a.button:not(.checkout) {
                background-color: #8cc63f !important;
                color: #fff !important;
            }

            .cart-drawer-body .woocommerce-mini-cart__buttons a.checkout {
                background-color: #7cb342 !important;
                color: #fff !important;
            }
        </style>
        <?php
    }
    add_action('wp_head', 'ruru_mini_cart_drawer_styles');
}