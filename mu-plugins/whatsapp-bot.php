<?php
/**
 * Plugin Name: Notificaciones CallMeBot WooCommerce
 * Description: Envía un mensaje de WhatsApp al administrador cuando hay un nuevo pedido. Incluye panel de configuración ligero.
 * Version: 1.1
 * Author: WP-CLI Init Script / RuruLabs
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * =========================================================
 * 1. OBTENER CONFIGURACIÓN
 * =========================================================
 */
function rurulab_cmb_get_options()
{
    $defaults = array(
        'telefono' => '51957834892',
        'apikey' => '6092547',
    );
    return wp_parse_args(get_option('rurulab_callmebot_settings', array()), $defaults);
}

/**
 * =========================================================
 * 2. REGISTRAR SUBMENÚ EN WOOCOMMERCE
 * =========================================================
 */
add_action('admin_menu', 'rurulab_cmb_add_submenu', 99);
function rurulab_cmb_add_submenu()
{
    add_submenu_page(
        'woocommerce',
        'RuruLab - CallMeBot',
        'RuruLab - CallMeBot',
        'manage_woocommerce',
        'rurulab-callmebot',
        'rurulab_cmb_admin_page'
    );
}

/**
 * =========================================================
 * 3. RENDERIZAR PANEL DE ADMINISTRACIÓN Y GUARDAR
 * =========================================================
 */
function rurulab_cmb_admin_page()
{
    // Seguridad
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    // Procesar guardado
    if (isset($_POST['rurulab_cmb_save']) && check_admin_referer('rurulab_cmb_nonce_action', 'rurulab_cmb_nonce')) {
        $options = array(
            'telefono' => sanitize_text_field($_POST['telefono']),
            'apikey' => sanitize_text_field($_POST['apikey']),
        );
        update_option('rurulab_callmebot_settings', $options);
        echo '<div class="notice notice-success is-dismissible"><p><strong>Configuración de CallMeBot guardada.</strong></p></div>';
    }

    $options = rurulab_cmb_get_options();
    ?>
    <div class="wrap">
        <h1 style="margin-bottom:20px;">📱 Alertas de Pedidos por WhatsApp (CallMeBot)</h1>

        <div style="display: flex; gap: 20px; flex-wrap: wrap;">

            <!-- FORMULARIO -->
            <div
                style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <form method="post" action="">
                    <?php wp_nonce_field('rurulab_cmb_nonce_action', 'rurulab_cmb_nonce'); ?>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="telefono">Número de WhatsApp</label></th>
                            <td>
                                <input name="telefono" type="text" id="telefono"
                                    value="<?php echo esc_attr($options['telefono']); ?>" class="regular-text"
                                    placeholder="51957834892" required />
                                <p class="description">Ingresa el número con código de país, sin espacios ni el signo "+".
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="apikey">CallMeBot API Key</label></th>
                            <td>
                                <input name="apikey" type="text" id="apikey"
                                    value="<?php echo esc_attr($options['apikey']); ?>" class="regular-text"
                                    placeholder="Ej: 6092547" required />
                                <p class="description">La clave que te entregó el bot de WhatsApp.</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Guardar Configuración', 'primary', 'rurulab_cmb_save'); ?>
                </form>
            </div>

            <!-- INSTRUCCIONES -->
            <div
                style="flex: 1; min-width: 300px; background: #f0f6fc; padding: 20px; border: 1px solid #c8d4e3; border-radius: 4px;">
                <h2 style="margin-top:0;">🤖 ¿Cómo conseguir tu API Key?</h2>
                <p>CallMeBot es un servicio gratuito. Para autorizar que te envíen mensajes, sigue estos 3 rápidos pasos:
                </p>
                <ol style="margin-left: 20px;">
                    <li>Agrega el número de teléfono <strong>+34 644 48 20 89</strong> a los contactos de tu celular
                        (nómbralo "CallMeBot").</li>
                    <li>Envíale un mensaje de WhatsApp a ese contacto con el siguiente texto exacto:<br>
                        <code
                            style="display:inline-block; margin-top:5px; background: #fff; padding: 4px 8px;">I allow callmebot to send me messages</code>
                    </li>
                    <li>El bot te responderá en segundos confirmando la activación y te entregará tu <strong>API
                            Key</strong> de 6 o 7 dígitos.</li>
                </ol>
                <p style="margin-bottom:0;"><em>Nota: Si el bot tarda en responder, intenta usar este otro número
                        alternativo de CallMeBot: +34 644 68 56 14</em></p>
            </div>

        </div>
    </div>
    <?php
}

/**
 * =========================================================
 * 4. LÓGICA DE NOTIFICACIÓN AL FINALIZAR PEDIDO
 * =========================================================
 */
add_action('woocommerce_checkout_order_processed', 'notificar_admin_callmebot', 10, 1);
function notificar_admin_callmebot($order_id)
{
    $options = rurulab_cmb_get_options();

    // Limpiar el teléfono de posibles símbolos extra y obtener apikey
    $phone = preg_replace('/[^0-9]/', '', $options['telefono']);
    $apikey = sanitize_text_field($options['apikey']);

    // Si no hay teléfono o API Key configurados, no hacer nada
    if (empty($phone) || empty($apikey)) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $cliente = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    $total = $order->get_currency() . ' ' . $order->get_total();
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
        'blocking' => false // Permite que el checkout del cliente no sufra demoras
    ));
}