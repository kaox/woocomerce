<?php
/**
 * Plugin Name: WooCommerce Pasarelas Manuales con Voucher
 * Description: Agrega pasarelas de Transferencia y Billeteras Digitales (Yape/Plin) con captura obligatoria, fix para FormData y columna en la lista de pedidos.
 * Version: 1.2
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==========================================
// 1. SCRIPT CHECKOUT (ENCTYPE & FORMDATA FIX)
// ==========================================
add_action('wp_footer', 'peru_checkout_enctype_script');
function peru_checkout_enctype_script()
{
    if (is_checkout()) {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                var $form = $('form.checkout');
                $form.attr('enctype', 'multipart/form-data');

                // Interceptar AJAX de WooCommerce checkout para adjuntar la imagen correctamente
                $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
                    if (options.url && options.url.indexOf('wc-ajax=checkout') !== -1) {
                        var selectedMethod = $('input[name="payment_method"]:checked').val();
                        if (selectedMethod === 'billeteras_digitales' || selectedMethod === 'transferencia_directa') {
                            var formData = new FormData($form[0]);
                            options.data = formData;
                            options.processData = false;
                            options.contentType = false;
                        }
                    }
                });
            });
        </script>
        <?php
    }
}

// ==========================================
// 2. REGISTRAR PASARELAS EN WOOCOMMERCE
// ==========================================
add_filter('woocommerce_payment_gateways', 'peru_agregar_pasarelas_manuales');
function peru_agregar_pasarelas_manuales($gateways)
{
    $gateways[] = 'WC_Gateway_Billeteras_Digitales';
    $gateways[] = 'WC_Gateway_Transferencia_Directa';
    return $gateways;
}

// ==========================================
// 3. DEFINIR CLASES DE LAS PASARELAS
// ==========================================
add_action('plugins_loaded', 'peru_init_pasarelas_manuales');
function peru_init_pasarelas_manuales()
{

    // --- PASARELA 1: Billeteras Digitales (Yape / Plin) ---
    class WC_Gateway_Billeteras_Digitales extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id = 'billeteras_digitales';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = 'Pago con Billeteras Digitales (Yape/Plin)';
            $this->method_description = 'Permite pagar con Yape o Plin y subir el comprobante de pago.';

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title', 'Pago con Billeteras Digitales');
            $this->description = $this->get_option('description', 'Realiza tu pago utilizando billeteras digitales como Yape, Plin, etc.');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Activar/Desactivar',
                    'type' => 'checkbox',
                    'label' => 'Activar Billeteras Digitales',
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => 'Título en el Checkout',
                    'type' => 'text',
                    'default' => 'Pago con Billeteras Digitales',
                ),
                'yape_numero' => array(
                    'title' => 'Número de Celular',
                    'type' => 'text',
                    'default' => '947197463',
                ),
                'yape_titular' => array(
                    'title' => 'Titular de la Cuenta',
                    'type' => 'text',
                    'default' => 'LOAVI DISTRIBUCIONES E.I.R.L.',
                ),
                'yape_qr' => array(
                    'title' => 'URL del Código QR',
                    'type' => 'text',
                    'description' => 'Enlace de la imagen del QR subida a la biblioteca de medios.',
                )
            );
        }

        public function payment_fields()
        {
            $numero = esc_html($this->get_option('yape_numero', '947197463'));
            $titular = esc_html($this->get_option('yape_titular', 'LOAVI DISTRIBUCIONES E.I.R.L.'));
            $qr_url = esc_url($this->get_option('yape_qr'));
            ?>
            <p style="margin-bottom:12px; font-size: 13px; color: #475569;">
                Realiza tu pago por <strong>Yape</strong> o <strong>Plin</strong> y adjunta el comprobante para procesar tu pedido.
            </p>

            <div
                style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 15px;">

                <!-- Indicador de Pago por Número (Yape y Plin) -->
                <div
                    style="margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 6px; flex-wrap: wrap;">
                    <span
                        style="background: #742284; color: #ffffff; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px; letter-spacing: 0.5px;">YAPE</span>
                    <span
                        style="background: #00A3E0; color: #ffffff; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px; letter-spacing: 0.5px;">PLIN</span>
                    <span style="font-size: 12px; color: #334155; font-weight: 600;">Pago por Número Celular</span>
                </div>

                <!-- Caja con Datos de Cuenta -->
                <div
                    style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 10px; margin-bottom: 14px;">
                    <p style="margin: 0 0 4px 0; font-size: 14px; color: #1e293b;">
                        <strong>Número:</strong> <span
                            style="font-size: 16px; color: #0f172a; font-weight: 700; letter-spacing: 0.5px;"><?php echo $numero; ?></span>
                    </p>
                    <p style="margin: 0; font-size: 12px; color: #64748b;">
                        <strong>Titular:</strong> <?php echo $titular; ?>
                    </p>
                </div>

                <!-- Código QR exclusivo de YAPE -->
                <?php if (!empty($qr_url)): ?>
                    <div style="margin-top: 12px; border-top: 1px solid #f1f5f9; padding-top: 12px;">
                        <div
                            style="display: inline-block; background: #f3e8ff; color: #6b21a8; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 12px; margin-bottom: 8px;">
                            📲 Escanea el QR únicamente desde YAPE
                        </div>
                        <div>
                            <img src="<?php echo $qr_url; ?>" alt="Código QR Yape"
                                style="max-width: 160px; height: auto; border-radius: 8px; border: 1px solid #e2e8f0; padding: 5px; background: #ffffff;" />
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Campo para Adjuntar Captura -->
            <div class="form-row form-row-wide" style="margin-top: 12px;">
                <label for="captura_billeteras"
                    style="font-weight: 600; font-size: 13px; color: #1e293b; display: block; margin-bottom: 5px;">
                    Adjuntar Captura de Pago <span class="required" style="color: #e11d48;">*</span>
                </label>
                <input type="file" name="captura_billeteras" id="captura_billeteras" accept="image/*,.pdf"
                    style="display: block; width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; font-size: 13px;" />
                <small style="color: #64748b; display: block; margin-top: 4px; font-size: 11px;">
                    Adjunta una captura de pantalla de la confirmación de tu pago (máx. 5MB).
                </small>
            </div>
            <?php
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $order->update_status('on-hold', __('Pago recibido vía Billetera Digital. En espera de verificación.', 'woocommerce'));
            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }
    }

    // --- PASARELA 2: Transferencia Bancaria ---
    class WC_Gateway_Transferencia_Directa extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id = 'transferencia_directa';
            $this->has_fields = true;
            $this->method_title = 'Pago por Transferencia Bancaria';
            $this->method_description = 'Permite pagar por transferencia bancaria directa y adjuntar voucher.';

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title', 'Pago por Transferencia Bancaria');
            $this->description = $this->get_option('description', 'Realiza tu transferencia a cualquiera de nuestras cuentas bancarias.');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Activar/Desactivar',
                    'type' => 'checkbox',
                    'label' => 'Activar Transferencia Bancaria',
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => 'Título en el Checkout',
                    'type' => 'text',
                    'default' => 'Pago por Transferencia Bancaria',
                ),
                'cuentas_info' => array(
                    'title' => 'Cuentas Bancarias',
                    'type' => 'textarea',
                    'default' => "BCP Soles: 191-XXXXXXX-0-XX\nCCI: 002191XXXXXXXXXX\nTitular: LOAVI DISTRIBUCIONES E.I.R.L.",
                )
            );
        }

        public function payment_fields()
        {
            echo '<p style="margin-bottom:12px; font-size:13px; color:#475569;">' . esc_html($this->description) . '</p>';
            echo '<div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin-bottom:15px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
            echo nl2br(esc_html($this->get_option('cuentas_info')));
            echo '</div>';

            echo '<div class="form-row form-row-wide" style="margin-top:12px;">';
            echo '<label for="captura_transferencia" style="font-weight:600; font-size:13px; color:#1e293b; display:block; margin-bottom:5px;">Adjuntar Captura de Pago <span class="required" style="color:#e11d48;">*</span></label>';
            echo '<input type="file" name="captura_transferencia" id="captura_transferencia" accept="image/*,.pdf" style="display:block; width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; background:#ffffff; font-size:13px;" />';
            echo '<small style="color:#64748b; display:block; margin-top:4px; font-size:11px;">Adjunta una captura del comprobante de transferencia (máx. 5MB).</small>';
            echo '</div>';
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $order->update_status('on-hold', __('Pago por transferencia bancaria en espera de validación de abono.', 'woocommerce'));
            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }
    }
}

// ==========================================
// 4. VALIDACIÓN DEL ADJUNTO
// ==========================================
add_action('woocommerce_checkout_process', 'peru_validar_captura_pago');
function peru_validar_captura_pago()
{
    $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';

    if ($payment_method === 'billeteras_digitales' || $payment_method === 'transferencia_directa') {
        $file_key = ($payment_method === 'billeteras_digitales') ? 'captura_billeteras' : 'captura_transferencia';

        if (empty($_FILES[$file_key]['name'])) {
            wc_add_notice(__('<strong>Atención:</strong> Por favor adjunta la captura de pantalla de tu pago para procesar el pedido.', 'woocommerce'), 'error');
        } else {
            $allowed_exts = array('jpg', 'jpeg', 'png', 'webp', 'pdf');
            $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed_exts)) {
                wc_add_notice(__('El formato de archivo no es permitido. Sube una imagen (JPG, PNG, WEBP) o PDF.', 'woocommerce'), 'error');
            }

            if ($_FILES[$file_key]['size'] > 5 * 1024 * 1024) {
                wc_add_notice(__('El archivo adjunto supera el tamaño máximo permitido de 5MB.', 'woocommerce'), 'error');
            }
        }
    }
}

// ==========================================
// 5. SUBIR Y VINCULAR ARCHIVO AL PEDIDO
// ==========================================
add_action('woocommerce_checkout_update_order_meta', 'peru_guardar_captura_pago');
function peru_guardar_captura_pago($order_id)
{
    $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';

    if ($payment_method === 'billeteras_digitales' || $payment_method === 'transferencia_directa') {
        $file_key = ($payment_method === 'billeteras_digitales') ? 'captura_billeteras' : 'captura_transferencia';

        if (!empty($_FILES[$file_key]['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $attachment_id = media_handle_upload($file_key, $order_id);

            if (!is_wp_error($attachment_id)) {
                $file_url = wp_get_attachment_url($attachment_id);
                update_post_meta($order_id, '_captura_pago_id', $attachment_id);
                update_post_meta($order_id, '_captura_pago_url', $file_url);

                $order = wc_get_order($order_id);
                $order->add_order_note('Comprobante de pago cargado: <a href="' . esc_url($file_url) . '" target="_blank">Ver Voucher</a>');
            }
        }
    }
}

// ==========================================
// 6. MOSTRAR COMPROBANTE EN ADMIN DE PEDIDO
// ==========================================
add_action('woocommerce_admin_order_data_after_billing_address', 'peru_mostrar_captura_pago_admin');
function peru_mostrar_captura_pago_admin($order)
{
    $file_url = $order->get_meta('_captura_pago_url');
    if ($file_url) {
        echo '<div style="margin-top:15px; padding:12px; background:#e7f3fe; border-left:4px solid #2196F3; border-radius:4px;">';
        echo '<strong>Comprobante de Pago Adjunto:</strong><br>';
        echo '<a href="' . esc_url($file_url) . '" target="_blank" class="button button-primary" style="margin-top:8px; display:inline-block;">🔍 Ver Comprobante</a>';
        echo '</div>';
    }
}

// ==========================================
// 7. COLUMNA MÉTODO DE PAGO EN LISTA GENERAL
// ==========================================
add_filter('manage_woocommerce_page_wc-orders_columns', 'peru_agregar_columna_pago');
add_filter('manage_edit-shop_order_columns', 'peru_agregar_columna_pago');
function peru_agregar_columna_pago($columns)
{
    $new_columns = array();
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key === 'order_status' || $key === 'status') {
            $new_columns['metodo_pago'] = 'Método de Pago';
        }
    }
    return $new_columns;
}

add_action('manage_woocommerce_page_wc-orders_custom_column', 'peru_mostrar_metodo_pago_columna', 10, 2);
add_action('manage_shop_order_posts_custom_column', 'peru_mostrar_metodo_pago_columna_legacy', 10, 2);

function peru_mostrar_metodo_pago_columna($column, $order)
{
    if ($column === 'metodo_pago') {
        $method_title = $order->get_payment_method_title();
        echo '<strong>' . esc_html($method_title ? $method_title : '—') . '</strong>';
    }
}

function peru_mostrar_metodo_pago_columna_legacy($column, $post_id)
{
    if ($column === 'metodo_pago') {
        $order = wc_get_order($post_id);
        if ($order) {
            $method_title = $order->get_payment_method_title();
            echo '<strong>' . esc_html($method_title ? $method_title : '—') . '</strong>';
        }
    }
}