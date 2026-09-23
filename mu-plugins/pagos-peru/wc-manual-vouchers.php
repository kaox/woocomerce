<?php
/**
 * Plugin Name: WooCommerce Pasarelas Manuales FREE
 * Description: Pasarelas para Billeteras Digitales (Yape/Plin) y Transferencias Bancarias (BCP, BBVA, Interbank, Scotiabank) con botones toggle. (Versión gratuita sin subida de comprobante).
 * Version: 2.1.0
 * Author: Tu Empresa
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==========================================
// 1. INICIALIZAR LAS PASARELAS
// ==========================================
add_action('plugins_loaded', 'wcmv_free_iniciar_pasarelas');
function wcmv_free_iniciar_pasarelas()
{

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // --- CLASE 1: BILLETERAS DIGITALES ---
    class WC_Gateway_WCMV_Billeteras_Free extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id = 'wcmv_billeteras_free';
            $this->has_fields = true;
            $this->method_title = 'Billeteras Digitales (Free)';
            $this->method_description = 'Pasarela para Yape y Plin con botones toggle.';

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title', 'Pago con Billeteras Digitales');
            $this->description = $this->get_option('description', 'Realiza tu pago con Yape o Plin.');

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
    class WC_Gateway_WCMV_Bancos_Free extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id = 'wcmv_bancos_free';
            $this->has_fields = true;
            $this->method_title = 'Transferencia Bancaria (Free)';
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
    add_filter('woocommerce_payment_gateways', 'wcmv_free_registrar_gateways');
    function wcmv_free_registrar_gateways($gateways)
    {
        $gateways[] = 'WC_Gateway_WCMV_Billeteras_Free';
        $gateways[] = 'WC_Gateway_WCMV_Bancos_Free';
        return $gateways;
    }
}

// ==========================================
// 2. VALIDACIÓN EN CHECKOUT
// ==========================================
add_action('woocommerce_checkout_process', 'wcmv_free_validar_pagos');
function wcmv_free_validar_pagos()
{
    if (isset($_POST['payment_method'])) {

        if ($_POST['payment_method'] === 'wcmv_billeteras_free') {
            if (empty($_POST['wcmv_billetera_seleccionada'])) {
                wc_add_notice('Por favor, selecciona una billetera digital.', 'error');
            }
        }

        if ($_POST['payment_method'] === 'wcmv_bancos_free') {
            if (empty($_POST['wcmv_banco_seleccionado'])) {
                wc_add_notice('Por favor, selecciona un banco para la transferencia.', 'error');
            }
        }
    }
}

// ==========================================
// 3. GUARDAR METADATOS (ACTUALIZADO HPOS)
// ==========================================
add_action('woocommerce_checkout_update_order_meta', 'wcmv_free_guardar_datos_orden');
function wcmv_free_guardar_datos_orden($order_id)
{
    if (!isset($_POST['payment_method']))
        return;

    $order = wc_get_order($order_id);
    if (!$order)
        return;

    $has_changes = false;

    // BILLETERAS
    if ($_POST['payment_method'] === 'wcmv_billeteras_free' && !empty($_POST['wcmv_billetera_seleccionada'])) {
        $metodo = strtoupper(sanitize_text_field($_POST['wcmv_billetera_seleccionada']));
        $order->update_meta_data('_tipo_pago_general', 'Billetera Digital');
        $order->update_meta_data('_metodo_seleccionado', $metodo);
        $has_changes = true;
        
        $order->add_order_note("El cliente ha seleccionado el pago mediante Billetera Digital ({$metodo}).");
    }

    // BANCOS
    if ($_POST['payment_method'] === 'wcmv_bancos_free' && !empty($_POST['wcmv_banco_seleccionado'])) {
        $metodo = strtoupper(sanitize_text_field($_POST['wcmv_banco_seleccionado']));
        $order->update_meta_data('_tipo_pago_general', 'Transferencia Bancaria');
        $order->update_meta_data('_metodo_seleccionado', $metodo);
        $has_changes = true;
        
        $order->add_order_note("El cliente ha seleccionado el pago mediante Transferencia Bancaria ({$metodo}).");
    }

    if ($has_changes) {
        $order->save();
    }
}

// ==========================================
// 4. MOSTRAR EN EL ADMINISTRADOR (VISTA "EDITAR PEDIDO")
// ==========================================
add_action('woocommerce_admin_order_data_after_billing_address', 'wcmv_free_mostrar_admin_info');
function wcmv_free_mostrar_admin_info($order)
{
    $tipo_general = $order->get_meta('_tipo_pago_general');
    $metodo_especifico = $order->get_meta('_metodo_seleccionado');

    if ($metodo_especifico) {
        echo '<div style="margin-top:20px; padding:15px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px;">';
        echo '<h3 style="margin-top:0; color:#0369a1; font-size:14px;">Detalles del Pago Manual</h3>';

        if ($tipo_general) {
            echo '<p style="margin:0 0 5px 0;"><strong>Tipo de Pago:</strong> ' . esc_html($tipo_general) . '</p>';
        }

        echo '<p style="margin:0 0 10px 0;"><strong>Método Específico:</strong> <span style="background:#0284c7; color:#fff; padding:3px 8px; border-radius:4px; font-weight:bold; font-size:12px;">' . esc_html($metodo_especifico) . '</span></p>';
        echo '</div>';
    }
}