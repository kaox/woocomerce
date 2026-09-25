<?php
/**
 * Plugin Name: WooCommerce Pasarelas Manuales PRO
 * Description: Pasarelas para Billeteras Digitales (Yape/Plin) y Transferencias Bancarias (BCP, BBVA, Interbank, Scotiabank) con botones toggle y subida de voucher aislada.
 * Version: 2.2.0
 * Author: Tu Empresa
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==========================================
// 1. SCRIPT PARA SUBIDA DE ARCHIVOS POR AJAX
// ==========================================
add_action('wp_footer', 'wcmv_pro_checkout_script');
function wcmv_pro_checkout_script()
{
    if (is_checkout() && !is_wc_endpoint_url('order-received')) {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                var $form = $('form.checkout');
                $form.attr('enctype', 'multipart/form-data');
                $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
                    if (options.url && options.url.indexOf('wc-ajax=checkout') !== -1) {
                        var selectedMethod = $('input[name="payment_method"]:checked').val();
                        if (selectedMethod === 'wcmv_billeteras_pro' || selectedMethod === 'wcmv_bancos_pro') {
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
// 2. INICIALIZAR LAS PASARELAS
// ==========================================
add_action('plugins_loaded', 'wcmv_pro_iniciar_pasarelas');
function wcmv_pro_iniciar_pasarelas()
{

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // --- CLASE 1: BILLETERAS DIGITALES ---
    class WC_Gateway_WCMV_Billeteras_Pro extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id = 'wcmv_billeteras_pro';
            $this->has_fields = true;
            $this->method_title = 'Billeteras Digitales';
            $this->method_description = 'Pasarela para Yape y Plin con botones toggle.';

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title', 'Pago con Billeteras Digitales');
            $this->description = $this->get_option('description', 'Realiza tu pago con Yape o Plin y adjunta tu comprobante.');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array('title' => 'Activar', 'type' => 'checkbox', 'default' => 'yes'),
                'title' => array('title' => 'Título', 'type' => 'text', 'default' => 'Pago con Billeteras Digitales'),

                // YAPE
                'yape_title' => array('title' => '--- YAPE ---', 'type' => 'title'),
                'yape_enabled' => array('title' => 'Habilitar Yape', 'type' => 'checkbox', 'default' => 'yes'),
                'yape_numero' => array('title' => 'Número Yape', 'type' => 'text'),
                'yape_titular' => array('title' => 'Titular Yape', 'type' => 'text'),
                'yape_qr' => array('title' => 'URL QR Yape', 'type' => 'text'),

                // PLIN
                'plin_title' => array('title' => '--- PLIN ---', 'type' => 'title'),
                'plin_enabled' => array('title' => 'Habilitar Plin', 'type' => 'checkbox', 'default' => 'yes'),
                'plin_numero' => array('title' => 'Número Plin', 'type' => 'text'),
                'plin_titular' => array('title' => 'Titular Plin', 'type' => 'text'),
                'plin_qr' => array('title' => 'URL QR Plin', 'type' => 'text'),
            );
        }

        public function payment_fields()
        {
            $opciones = array();
            if ($this->get_option('yape_enabled') === 'yes')
                $opciones['yape'] = array('nombre' => 'Yape', 'color' => '#742284');
            if ($this->get_option('plin_enabled') === 'yes')
                $opciones['plin'] = array('nombre' => 'Plin', 'color' => '#00A3E0');

            if (empty($opciones)) {
                echo '<p>No hay billeteras configuradas.</p>';
                return;
            }

            echo '<style>
                .wcmv-toggles { display: flex; gap: 10px; margin-bottom: 15px; }
                .wcmv-toggle-btn { flex: 1; }
                .wcmv-toggle-btn input { display: none !important; }
                .wcmv-toggle-btn label { display: block; text-align: center; padding: 10px; border: 2px solid #cbd5e1; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.2s; color: #475569; }
                .wcmv-toggle-btn input[value="yape"]:checked + label { border-color: #742284; background: #742284; color: white; }
                .wcmv-toggle-btn input[value="plin"]:checked + label { border-color: #00A3E0; background: #00A3E0; color: white; }
            </style>';

            echo '<div class="wcmv-toggles">';
            foreach ($opciones as $key => $data) {
                echo '<div class="wcmv-toggle-btn">';
                echo '<input type="radio" name="wcmv_billetera_seleccionada" id="billetera_' . $key . '" value="' . $key . '">';
                echo '<label for="billetera_' . $key . '">' . $data['nombre'] . '</label>';
                echo '</div>';
            }
            echo '</div>';

            foreach ($opciones as $key => $data) {
                $numero = esc_html($this->get_option($key . '_numero'));
                $titular = esc_html($this->get_option($key . '_titular'));
                $qr = esc_url($this->get_option($key . '_qr'));

                echo '<div id="wcmv_datos_' . $key . '" class="wcmv_datos_billetera" style="display:none; text-align: center; background:#f8fafc; padding:15px; border-radius:8px; border:1px dashed #cbd5e1; margin-bottom:15px;">';
                echo '<p style="margin:0 0 5px 0;"><strong>Número:</strong> <span style="font-size:18px; font-weight:bold; color:' . $data['color'] . ';">' . $numero . '</span></p>';
                echo '<p style="margin:0; font-size:13px;"><strong>Titular:</strong> ' . $titular . '</p>';
                if (!empty($qr)) {
                    echo '<img src="' . $qr . '" style="max-width:140px; margin-top:10px; border-radius:8px;" />';
                }
                echo '</div>';
            }

            echo '<div style="margin-top: 15px;">';
            echo '<label style="font-weight: bold; display:block; margin-bottom:5px;">Adjuntar Captura <span style="color:red;">*</span></label>';
            echo '<input type="file" name="wcmv_voucher_billetera" accept="image/*,application/pdf" style="width:100%; border:1px solid #ccc; padding:5px; border-radius:4px;"/>';
            echo '</div>';

            ?>
            <script>
                jQuery(document).ready(function ($) {
                    $('input[name="wcmv_billetera_seleccionada"]').change(function () {
                        $('.wcmv_datos_billetera').slideUp(200);
                        $('#wcmv_datos_' + $(this).val()).slideDown(200);
                    });
                });
            </script>
            <?php
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $order->update_status('on-hold', 'Pago con billetera en espera.');
            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();
            return array('result' => 'success', 'redirect' => $this->get_return_url($order));
        }
    }

    // --- CLASE 2: TRANSFERENCIAS BANCARIAS ---
    class WC_Gateway_WCMV_Bancos_Pro extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id = 'wcmv_bancos_pro';
            $this->has_fields = true;
            $this->method_title = 'Transferencia Bancaria';
            $this->method_description = 'Pasarela para BCP, BBVA, Interbank y Scotiabank.';

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title', 'Transferencia Bancaria');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array('title' => 'Activar', 'type' => 'checkbox', 'default' => 'yes'),
                'title' => array('title' => 'Título', 'type' => 'text', 'default' => 'Transferencia Bancaria Directa'),
            );

            $bancos = array('bcp' => 'BCP', 'bbva' => 'BBVA', 'interbank' => 'Interbank', 'scotiabank' => 'Scotiabank');

            foreach ($bancos as $key => $nombre) {
                $this->form_fields[$key . '_title'] = array('title' => '--- ' . $nombre . ' ---', 'type' => 'title');
                $this->form_fields[$key . '_enabled'] = array('title' => 'Habilitar ' . $nombre, 'type' => 'checkbox', 'default' => 'yes');
                $this->form_fields[$key . '_titular'] = array('title' => 'Titular de Cuenta', 'type' => 'text');
                $this->form_fields[$key . '_cuenta'] = array('title' => 'Número de Cuenta', 'type' => 'text');
                $this->form_fields[$key . '_cci'] = array('title' => 'CCI', 'type' => 'text');
            }
        }

        public function payment_fields()
        {
            $bancos_disp = array();
            $bancos_config = array('bcp' => 'BCP', 'bbva' => 'BBVA', 'interbank' => 'Interbank', 'scotiabank' => 'Scotiabank');

            foreach ($bancos_config as $key => $nombre) {
                if ($this->get_option($key . '_enabled') === 'yes') {
                    $bancos_disp[$key] = $nombre;
                }
            }

            if (empty($bancos_disp)) {
                echo '<p>No hay bancos configurados.</p>';
                return;
            }

            echo '<style>
                .wcmv-toggles-banco { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; }
                .wcmv-toggle-banco-btn { flex: 1 1 45%; }
                .wcmv-toggle-banco-btn input { display: none !important; }
                .wcmv-toggle-banco-btn label { display: block; text-align: center; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; font-size:13px; font-weight: 600; background: #fff; transition: 0.2s; }
                .wcmv-toggle-banco-btn input:checked + label { border-color: #0f172a; background: #0f172a; color: white; }
            </style>';

            echo '<div class="wcmv-toggles-banco">';
            foreach ($bancos_disp as $key => $nombre) {
                echo '<div class="wcmv-toggle-banco-btn">';
                echo '<input type="radio" name="wcmv_banco_seleccionado" id="banco_' . $key . '" value="' . $key . '">';
                echo '<label for="banco_' . $key . '">' . $nombre . '</label>';
                echo '</div>';
            }
            echo '</div>';

            foreach ($bancos_disp as $key => $nombre) {
                $titular = esc_html($this->get_option($key . '_titular'));
                $cuenta = esc_html($this->get_option($key . '_cuenta'));
                $cci = esc_html($this->get_option($key . '_cci'));

                echo '<div id="wcmv_datos_' . $key . '" class="wcmv_datos_banco" style="display:none; background:#f1f5f9; padding:12px; border-radius:6px; border-left:4px solid #0f172a; margin-bottom:15px; font-size:13px;">';
                echo '<p style="margin:0 0 4px 0;"><strong>Banco:</strong> ' . $nombre . '</p>';
                echo '<p style="margin:0 0 4px 0;"><strong>Titular:</strong> ' . $titular . '</p>';
                echo '<p style="margin:0 0 4px 0;"><strong>Cuenta:</strong> <span style="font-size:15px; font-weight:bold;">' . $cuenta . '</span></p>';
                if (!empty($cci)) {
                    echo '<p style="margin:0;"><strong>CCI:</strong> ' . $cci . '</p>';
                }
                echo '</div>';
            }

            echo '<div style="margin-top: 15px;">';
            echo '<label style="font-weight: bold; display:block; margin-bottom:5px;">Adjuntar Voucher <span style="color:red;">*</span></label>';
            echo '<input type="file" name="wcmv_voucher_banco" accept="image/*,application/pdf" style="width:100%; border:1px solid #ccc; padding:5px; border-radius:4px;"/>';
            echo '</div>';

            ?>
            <script>
                jQuery(document).ready(function ($) {
                    $('input[name="wcmv_banco_seleccionado"]').change(function () {
                        $('.wcmv_datos_banco').slideUp(200);
                        $('#wcmv_datos_' + $(this).val()).slideDown(200);
                    });
                });
            </script>
            <?php
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $order->update_status('on-hold', 'Pago por transferencia en espera.');
            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();
            return array('result' => 'success', 'redirect' => $this->get_return_url($order));
        }
    }

    // --- REGISTRAR AMBAS PASARELAS ---
    add_filter('woocommerce_payment_gateways', 'wcmv_pro_registrar_gateways');
    function wcmv_pro_registrar_gateways($gateways)
    {
        $gateways[] = 'WC_Gateway_WCMV_Billeteras_Pro';
        $gateways[] = 'WC_Gateway_WCMV_Bancos_Pro';
        return $gateways;
    }
}

// ==========================================
// 3. VALIDACIÓN EN CHECKOUT
// ==========================================
add_action('woocommerce_checkout_process', 'wcmv_pro_validar_pagos');
function wcmv_pro_validar_pagos()
{
    if (isset($_POST['payment_method'])) {

        if ($_POST['payment_method'] === 'wcmv_billeteras_pro') {
            if (empty($_POST['wcmv_billetera_seleccionada'])) {
                wc_add_notice('Por favor, selecciona una billetera digital.', 'error');
            }
            if (empty($_FILES['wcmv_voucher_billetera']['name'])) {
                wc_add_notice('Debes adjuntar la captura de tu pago.', 'error');
            }
        }

        if ($_POST['payment_method'] === 'wcmv_bancos_pro') {
            if (empty($_POST['wcmv_banco_seleccionado'])) {
                wc_add_notice('Por favor, selecciona un banco para la transferencia.', 'error');
            }
            if (empty($_FILES['wcmv_voucher_banco']['name'])) {
                wc_add_notice('Debes adjuntar el voucher de tu transferencia.', 'error');
            }
        }
    }
}

// ==========================================
// 4. GUARDAR METADATOS Y ARCHIVO (RENOMBRADO + CARPETA AISLADA)
// ==========================================
add_action('woocommerce_checkout_update_order_meta', 'wcmv_pro_guardar_datos_orden');
function wcmv_pro_guardar_datos_orden($order_id)
{
    if (!isset($_POST['payment_method']))
        return;

    $order = wc_get_order($order_id);
    if (!$order)
        return;

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $has_changes = false;

    // Función para desviar temporalmente los vouchers a una carpeta privada
    $wcmv_directorio_vouchers = function ($param) {
        $subdir = '/vouchers_pro';
        $param['path'] = $param['basedir'] . $subdir;
        $param['url'] = $param['baseurl'] . $subdir;
        $param['subdir'] = $subdir;
        return $param;
    };

    // Función auxiliar para procesar la subida y compresión
    $procesar_voucher = function ($archivo, $order_id) use ($wcmv_directorio_vouchers) {
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $archivo['name'] = 'voucher-pedido-' . $order_id . '-' . time() . '.' . $extension;

        add_filter('upload_dir', $wcmv_directorio_vouchers);
        $movefile = wp_handle_upload($archivo, array('test_form' => false));
        remove_filter('upload_dir', $wcmv_directorio_vouchers);

        if ($movefile && !isset($movefile['error'])) {

            // MOTOR DE COMPRESIÓN DE IMAGEN
            $file_type = wp_check_filetype($movefile['file']);
            if (strpos($file_type['type'], 'image/') === 0) {
                $image = wp_get_image_editor($movefile['file']);
                if (!is_wp_error($image)) {
                    // Reducir tamaño máximo a 1000px y mantener proporción
                    $image->resize(1000, 1000, false);
                    // Reducir calidad JPEG/WebP a 75%
                    $image->set_quality(75);
                    // Guardar los cambios sobre el mismo archivo
                    $image->save($movefile['file']);
                }
            }

            return $movefile['url'];
        }
        return false;
    };


    // BILLETERAS
    if ($_POST['payment_method'] === 'wcmv_billeteras_pro' && !empty($_POST['wcmv_billetera_seleccionada'])) {
        $metodo = strtoupper(sanitize_text_field($_POST['wcmv_billetera_seleccionada']));
        $order->update_meta_data('_tipo_pago_general', 'Billetera Digital');
        $order->update_meta_data('_metodo_seleccionado', $metodo);
        $has_changes = true;

        if (!empty($_FILES['wcmv_voucher_billetera']['name'])) {
            $archivo = $_FILES['wcmv_voucher_billetera'];

            // Renombrado Inteligente
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $archivo['name'] = 'voucher-pedido-' . $order_id . '-' . time() . '.' . $extension;

            // Desviar a carpeta oculta
            add_filter('upload_dir', $wcmv_directorio_vouchers);
            $movefile = wp_handle_upload($archivo, array('test_form' => false));
            remove_filter('upload_dir', $wcmv_directorio_vouchers);

            if ($movefile && !isset($movefile['error'])) {
                $order->update_meta_data('_captura_pago_url', $movefile['url']);
            } else {
                $order->add_order_note("Error al subir comprobante: " . $movefile['error']);
            }
        }
        $order->add_order_note("El cliente ha reportado un pago mediante Billetera Digital ({$metodo}). Revisa los detalles para ver el comprobante.");
    }

    // BANCOS
    if ($_POST['payment_method'] === 'wcmv_bancos_pro' && !empty($_POST['wcmv_banco_seleccionado'])) {
        $metodo = strtoupper(sanitize_text_field($_POST['wcmv_banco_seleccionado']));
        $order->update_meta_data('_tipo_pago_general', 'Transferencia Bancaria');
        $order->update_meta_data('_metodo_seleccionado', $metodo);
        $has_changes = true;

        if (!empty($_FILES['wcmv_voucher_banco']['name'])) {
            $archivo = $_FILES['wcmv_voucher_banco'];

            // Renombrado Inteligente
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $archivo['name'] = 'voucher-pedido-' . $order_id . '-' . time() . '.' . $extension;

            // Desviar a carpeta oculta
            add_filter('upload_dir', $wcmv_directorio_vouchers);
            $movefile = wp_handle_upload($archivo, array('test_form' => false));
            remove_filter('upload_dir', $wcmv_directorio_vouchers);

            if ($movefile && !isset($movefile['error'])) {
                $order->update_meta_data('_captura_pago_url', $movefile['url']);
            } else {
                $order->add_order_note("Error al subir comprobante: " . $movefile['error']);
            }
        }
        $order->add_order_note("El cliente ha reportado un pago mediante Transferencia Bancaria ({$metodo}). Revisa los detalles para ver el comprobante.");
    }

    if ($has_changes) {
        $order->save();
    }
}

// ==========================================
// 5. MOSTRAR EN EL ADMINISTRADOR (VISTA "EDITAR PEDIDO")
// ==========================================
add_action('woocommerce_admin_order_data_after_billing_address', 'wcmv_pro_mostrar_admin_info');
function wcmv_pro_mostrar_admin_info($order)
{
    $tipo_general = $order->get_meta('_tipo_pago_general');
    $metodo_especifico = $order->get_meta('_metodo_seleccionado');
    $voucher_url = $order->get_meta('_captura_pago_url');

    if ($metodo_especifico) {
        echo '<div style="margin-top:20px; padding:15px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px;">';
        echo '<h3 style="margin-top:0; color:#0369a1; font-size:14px;">Detalles del Pago Manual</h3>';

        if ($tipo_general) {
            echo '<p style="margin:0 0 5px 0;"><strong>Tipo de Pago:</strong> ' . esc_html($tipo_general) . '</p>';
        }

        echo '<p style="margin:0 0 10px 0;"><strong>Método Específico:</strong> <span style="background:#0284c7; color:#fff; padding:3px 8px; border-radius:4px; font-weight:bold; font-size:12px;">' . esc_html($metodo_especifico) . '</span></p>';

        if ($voucher_url) {
            echo '<p style="margin-top:15px;"><a href="' . esc_url($voucher_url) . '" target="_blank" class="button button-primary" style="background:#16a34a; border-color:#16a34a; color:#fff; text-decoration:none;">📸 Ver Comprobante Subido</a></p>';
        } else {
            echo '<p style="color:red; font-weight:bold;">⚠️ No se encontró el comprobante adjunto.</p>';
        }

        echo '</div>';
    }
}