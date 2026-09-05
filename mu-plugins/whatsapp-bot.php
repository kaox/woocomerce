<?php
/**
 * Plugin Name: Notificaciones CallMeBot WooCommerce
 * Description: Envía un mensaje de WhatsApp al administrador cuando hay un nuevo pedido.
 * Author: WP-CLI Init Script
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_checkout_order_processed', 'notificar_admin_callmebot', 10, 1);
function notificar_admin_callmebot($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order)
        return;

    $phone = '51957834892'; // Tu número de WhatsApp con código de país (sin el +)
    $apikey = '6092547';

    $cliente = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    $total = $order->get_currency() . ' ' . $order->get_total();

    // Obtener el nombre legible del estado (ej. "En espera", "Procesando", "Pendiente de pago")
    $estado = wc_get_order_status_name($order->get_status());
    $metodo = $order->get_payment_method_title();

    $mensaje = "🛒 *Nuevo Pedido #{$order_id}*\n";
    $mensaje .= "👤 Cliente: {$cliente}\n";
    $mensaje .= "💰 Total: {$total}\n";
    $mensaje .= "💳 Método: {$metodo}\n";
    $mensaje .= "📌 Estado: {$estado}";

    // Construir la URL con el mensaje codificado
    $url = "https://api.callmebot.com/whatsapp.php?phone={$phone}&text=" . urlencode($mensaje) . "&apikey={$apikey}";

    // Petición HTTP asíncrona no bloqueante
    wp_remote_get($url, array(
        'timeout' => 10,
        'blocking' => false // Permite que el checkout del cliente no sufra demoras de carga
    ));
}