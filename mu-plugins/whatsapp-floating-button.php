<?php
/*
  Plugin Name: Botón Flotante de WhatsApp Custom
  Description: Agrega un botón de WhatsApp ultraligero e independiente del tema activo con panel de control en WooCommerce.
  Version: 1.1
*/

if (!defined('ABSPATH')) {
    exit; // Seguridad
}

// 1. Obtener opciones almacenadas o valores por defecto
function rurulab_wa_get_options()
{
    $defaults = array(
        'enabled' => 1,
        'telefono' => '51947197463',
        'mensaje' => 'Hola, necesito ayuda para elegir mis productos.',
        'mensaje_web' => '¿Te ayudamos?',
    );
    return wp_parse_args(get_option('rurulab_wa_settings', array()), $defaults);
}

// 2. Registrar submenú bajo WooCommerce
add_action('admin_menu', 'rurulab_wa_add_submenu', 99);
function rurulab_wa_add_submenu()
{
    add_submenu_page(
        'woocommerce',
        'RuruLab - WhatsApp',
        'RuruLab - WhatsApp',
        'manage_woocommerce',
        'rurulab-wa-button',
        'rurulab_wa_options_page'
    );
}

// 3. Renderizar el panel de administración y procesar guardado
function rurulab_wa_options_page()
{
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    // Procesar envío del formulario de manera segura
    if (isset($_POST['rurulab_wa_save']) && check_admin_referer('rurulab_wa_nonce_action', 'rurulab_wa_nonce')) {
        $updated_options = array(
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
            'telefono' => sanitize_text_field($_POST['telefono']),
            'mensaje' => sanitize_text_field($_POST['mensaje']),
            'mensaje_web' => sanitize_text_field($_POST['mensaje_web']),
        );
        update_option('rurulab_wa_settings', $updated_options);
        echo '<div class="notice notice-success is-dismissible"><p><strong>Configuración de WhatsApp guardada.</strong></p></div>';
    }

    $options = rurulab_wa_get_options();
    ?>
    <div class="wrap">
        <h1>Configuración de Botón Flotante de WhatsApp</h1>
        <form method="post" action="">
            <?php wp_nonce_field('rurulab_wa_nonce_action', 'rurulab_wa_nonce'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Estado</th>
                    <td>
                        <label for="enabled">
                            <input type="checkbox" id="enabled" name="enabled" value="1" <?php checked(1, $options['enabled']); ?> />
                            Habilitar botón flotante en el sitio web
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="telefono">Teléfono</label></th>
                    <td>
                        <input name="telefono" type="text" id="telefono"
                            value="<?php echo esc_attr($options['telefono']); ?>" class="regular-text"
                            placeholder="51947197463" required />
                        <p class="description">Ingresa el número con código de país, sin espacios ni el signo "+".</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mensaje_web">Mensaje Web (Etiqueta)</label></th>
                    <td>
                        <input name="mensaje_web" type="text" id="mensaje_web"
                            value="<?php echo esc_attr($options['mensaje_web']); ?>" class="regular-text"
                            placeholder="¿Te ayudamos?" />
                        <p class="description">Texto visible que flota junto al ícono de WhatsApp.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mensaje">Mensaje de WhatsApp</label></th>
                    <td>
                        <textarea name="mensaje" id="mensaje" rows="3"
                            class="large-text"><?php echo esc_textarea($options['mensaje']); ?></textarea>
                        <p class="description">Texto predeterminado con el que iniciará el chat en WhatsApp.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Guardar Cambios', 'primary', 'rurulab_wa_save'); ?>
        </form>
    </div>
    <?php
}

// 4. Inyectar CSS en el <head> (solo si está habilitado)
add_action('wp_head', 'custom_wa_button_css');
function custom_wa_button_css()
{
    $options = rurulab_wa_get_options();
    if (empty($options['enabled'])) {
        return;
    }
    ?>
    <style id="rurulab-wa-custom-styles">
        .rurulab-wa-wrapper {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 99999;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }

        .rurulab-wa-message {
            background-color: #ffffff;
            color: #333333;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            white-space: nowrap;
        }

        .rurulab-wa-button {
            background-color: #25d366;
            color: #ffffff;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
        }

        .rurulab-wa-button:hover {
            transform: scale(1.08);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
            color: #ffffff;
        }

        .rurulab-wa-button svg {
            width: 32px;
            height: 32px;
        }

        .visually-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
    </style>
    <?php
}

// 5. Inyectar HTML en el <footer> (solo si está habilitado)
add_action('wp_footer', 'custom_wa_button_html');
function custom_wa_button_html()
{
    $options = rurulab_wa_get_options();
    if (empty($options['enabled'])) {
        return;
    }

    $telefono = preg_replace('/[^0-9]/', '', $options['telefono']);
    $mensaje = $options['mensaje'];
    $mensaje_web = $options['mensaje_web'];
    $url_wa = 'https://wa.me/' . $telefono . '?text=' . rawurlencode($mensaje);
    ?>
    <div class="rurulab-wa-wrapper">
        <?php if (!empty($mensaje_web)): ?>
            <span class="rurulab-wa-message" aria-hidden="true"><?php echo esc_html($mensaje_web); ?></span>
        <?php endif; ?>

        <a class="rurulab-wa-button" href="<?php echo esc_url($url_wa); ?>" target="_blank" rel="noopener noreferrer"
            aria-label="Consultar por WhatsApp">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path
                    d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479s1.065 2.875 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.262.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.89-9.884a9.83 9.83 0 0 1 7.033 2.914 9.83 9.83 0 0 1 2.908 7.038c-.002 5.45-4.437 9.884-9.888 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.3-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.82 11.82 0 0 0-3.466-8.413Z">
                </path>
            </svg>
            <span class="visually-hidden">WhatsApp</span>
        </a>
    </div>
    <?php
}