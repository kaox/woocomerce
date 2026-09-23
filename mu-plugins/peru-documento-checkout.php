<?php
/**
 * Plugin Name: WooCommerce Perú - Campo Único DNI/RUC
 * Description: Agrega un campo unificado de DNI (8 dígitos) o RUC (11 dígitos) con validación automática.
 * Version: 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Crear el campo único en el checkout de WooCommerce
add_filter('woocommerce_checkout_fields', 'peru_add_document_checkout_field');
function peru_add_document_checkout_field($fields)
{
    $fields['billing']['billing_dni_ruc'] = array(
        'type' => 'text',
        'label' => __('DNI o RUC', 'woocommerce'),
        'placeholder' => __('Ingrese 8 dígitos (DNI) o 11 dígitos (RUC)', 'woocommerce'),
        'required' => true,
        'class' => array('form-row-wide'),
        'clear' => true,
        'priority' => 35,
        'custom_attributes' => array(
            'maxlength' => '11',
            'inputmode' => 'numeric',
            'pattern' => '[0-9]*',
            'oninput' => "this.value = this.value.replace(/[^0-9]/g, '');"
        ),
    );
    return $fields;
}

// 2. Validar la cantidad de dígitos al procesar el checkout
add_action('woocommerce_checkout_process', 'peru_validate_document_checkout_field');
function peru_validate_document_checkout_field()
{
    if (!empty($_POST['billing_dni_ruc'])) {
        // Limpiar para dejar únicamente números
        $doc = preg_replace('/\D/', '', $_POST['billing_dni_ruc']);
        $length = strlen($doc);

        if ($length !== 8 && $length !== 11) {
            wc_add_notice(__('<strong>Número de documento inválido:</strong> Debe ingresar un DNI de 8 dígitos o un RUC de 11 dígitos.', 'woocommerce'), 'error');
        }
    }
}

// 3. Guardar el tipo de documento y el número en el pedido
add_action('woocommerce_checkout_update_order_meta', 'peru_save_document_checkout_field');
function peru_save_document_checkout_field($order_id)
{
    if (!empty($_POST['billing_dni_ruc'])) {
        $doc = preg_replace('/\D/', '', $_POST['billing_dni_ruc']);
        $type = (strlen($doc) === 11) ? 'RUC' : 'DNI';

        update_post_meta($order_id, '_billing_doc_type', $type);
        update_post_meta($order_id, '_billing_doc_number', $doc);
        update_post_meta($order_id, '_billing_dni_ruc', $type . ': ' . $doc);
    }
}

// 4. Mostrar el documento en el panel de administración del pedido
add_action('woocommerce_admin_order_data_after_billing_address', 'peru_display_document_admin_order');
function peru_display_document_admin_order($order)
{
    $doc_info = $order->get_meta('_billing_dni_ruc');
    if ($doc_info) {
        echo '<p><strong>Documento de Identidad (Perú):</strong> ' . esc_html($doc_info) . '</p>';
    }
}

// 5. Incluir el documento en los correos electrónicos de confirmación
add_filter('woocommerce_email_order_meta_keys', 'peru_display_document_in_emails');
function peru_display_document_in_emails($keys)
{
    $keys['Documento (DNI/RUC)'] = '_billing_dni_ruc';
    return $keys;
}