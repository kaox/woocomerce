<?php
/**
 * Plugin Name: OV - Banner Superior RuruLabs
 * Description: Banner superior configurable desde el administrador de WordPress. Optimizado para alto rendimiento.
 * Version: 3.1.0
 * Author: RuruLabs
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * =========================================================
 * 1. CONFIGURACIÓN Y OPCIONES
 * =========================================================
 */
function ov_banner_get_options()
{
    $defaults = array(
        'activo' => 1,
        'texto' => 'ENVÍO GRATIS EN PAÑALES POR COMPRAS DESDE S/ 120.00   •   OFERTAS EXCLUSIVAS EN PACKS x2, x3 y x4   •  ',
        'fondo' => '#0eb8b1',
        'color' => '#ffffff',
    );
    $options = get_option('ov_banner_options', array());
    return wp_parse_args(is_array($options) ? $options : array(), $defaults);
}

/**
 * =========================================================
 * 2. ADMINISTRACIÓN: MENÚ Y ASSETS
 * =========================================================
 */
add_action('admin_menu', 'rurulab_ov_banner_add_submenu', 99);
function rurulab_ov_banner_add_submenu()
{
    add_submenu_page(
        'woocommerce',
        'RuruLab - Banner Superior',
        'RuruLab - Banner Superior',
        'manage_woocommerce',
        'rurulab-ov-banner',
        'ov_banner_admin_page'
    );
}

add_action('admin_enqueue_scripts', 'ov_banner_admin_assets');
function ov_banner_admin_assets($hook)
{
    // FIX: Cargar solo en la página correcta de WooCommerce
    if ('woocommerce_page_rurulab-ov-banner' !== $hook) {
        return;
    }
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
}

/**
 * =========================================================
 * 3. GUARDAR CONFIGURACIÓN
 * =========================================================
 */
add_action('admin_init', 'ov_banner_save_settings');
function ov_banner_save_settings()
{
    if (!current_user_can('manage_options') || !isset($_POST['ov_banner_save']))
        return;

    if (!isset($_POST['ov_banner_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ov_banner_nonce'])), 'ov_banner_save_settings')) {
        return;
    }

    $fondo = isset($_POST['fondo']) ? sanitize_hex_color(wp_unslash($_POST['fondo'])) : '';
    $color = isset($_POST['color']) ? sanitize_hex_color(wp_unslash($_POST['color'])) : '';

    $options = array(
        'activo' => isset($_POST['activo']) ? 1 : 0,
        'texto' => isset($_POST['texto']) ? sanitize_textarea_field(wp_unslash($_POST['texto'])) : '',
        'fondo' => $fondo ? $fondo : '#0eb8b1',
        'color' => $color ? $color : '#ffffff',
    );

    update_option('ov_banner_options', $options);
    add_settings_error('ov_banner_messages', 'ov_banner_message', 'Configuración guardada correctamente.', 'updated');
}

/**
 * =========================================================
 * 4. PÁGINA DE ADMINISTRACIÓN (HTML/CSS/JS)
 * =========================================================
 */
function ov_banner_admin_page()
{
    if (!current_user_can('manage_options'))
        return;
    $options = ov_banner_get_options();
    settings_errors('ov_banner_messages');
    ?>
    <div class="wrap">
        <h1 style="margin-bottom:20px;">📣 Banner Superior</h1>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:25px;max-width:900px;">
            <form method="post">
                <?php wp_nonce_field('ov_banner_save_settings', 'ov_banner_nonce'); ?>

                <div
                    style="display:flex;align-items:center;justify-content:space-between;padding:15px 0 25px;border-bottom:1px solid #eee;margin-bottom:25px;">
                    <div>
                        <strong style="display:block;font-size:16px;margin-bottom:5px;">Activar banner</strong>
                        <span style="color:#646970;">Activa o desactiva el banner sin eliminar su configuración.</span>
                    </div>
                    <label class="ov-switch" style="position:relative;display:inline-block;width:52px;height:28px;">
                        <input type="checkbox" name="activo" id="ov_banner_activo" value="1" <?php checked($options['activo'], 1); ?> style="opacity:0;width:0;height:0;">
                        <span class="ov-slider"
                            style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;transition:.3s;border-radius:28px;"></span>
                    </label>
                </div>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="ov_banner_texto">Texto del banner</label></th>
                        <td>
                            <textarea name="texto" id="ov_banner_texto" rows="3" class="large-text"
                                placeholder="Escribe el texto que aparecerá en el banner..."><?php echo esc_textarea($options['texto']); ?></textarea>
                            <p class="description">Puedes utilizar • para separar diferentes mensajes.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ov_banner_fondo">Color de fondo</label></th>
                        <td><input type="text" name="fondo" id="ov_banner_fondo"
                                value="<?php echo esc_attr($options['fondo']); ?>" class="ov-color-picker"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ov_banner_color">Color del texto</label></th>
                        <td><input type="text" name="color" id="ov_banner_color"
                                value="<?php echo esc_attr($options['color']); ?>" class="ov-color-picker"></td>
                    </tr>
                </table>

                <h2 style="margin-top:30px;">Vista previa</h2>
                <div id="ov-banner-preview"
                    style="width:100%;overflow:hidden;padding:14px 0;border-radius:4px;font-weight:600;font-size:14px;white-space:nowrap;box-sizing:border-box;margin-bottom:25px;">
                    <div id="ov-banner-preview-text" style="display:inline-block;padding-left:100%;">
                        <?php echo esc_html($options['texto']); ?></div>
                </div>

                <?php submit_button('Guardar cambios', 'primary', 'ov_banner_save'); ?>
            </form>
        </div>
    </div>

    <style>
        .ov-switch input:checked+.ov-slider {
            background: #2271b1 !important
        }

        .ov-switch input:checked+.ov-slider:before {
            transform: translateX(24px)
        }

        .ov-slider:before {
            content: "";
            position: absolute;
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .25)
        }

        #ov-banner-preview-text {
            animation: ovBannerPreview 10s linear infinite
        }

        @keyframes ovBannerPreview {
            0% {
                transform: translateX(0)
            }

            100% {
                transform: translateX(-100%)
            }
        }
    </style>

    <script>
        jQuery(document).ready(function ($) {
            function actualizarPreview() {
                $('#ov-banner-preview').css({ 'background-color': $('#ov_banner_fondo').val(), 'color': $('#ov_banner_color').val() });
                $('#ov-banner-preview-text').text($('#ov_banner_texto').val());
            }
            $('.ov-color-picker').wpColorPicker({ change: actualizarPreview, clear: actualizarPreview });
            $('#ov_banner_texto').on('input', actualizarPreview);
            actualizarPreview();
        });
    </script>
    <?php
}

/**
 * =========================================================
 * 5. FRONTEND: RENDERIZAR BANNER Y CSS MINIFICADO
 * =========================================================
 */
add_action('wp_body_open', 'ov_banner_render_frontend', 5);
function ov_banner_render_frontend()
{
    if (is_admin())
        return;

    $options = ov_banner_get_options();

    // No procesar nada si está apagado o vacío
    if (empty($options['activo']) || empty(trim($options['texto']))) {
        return;
    }

    $texto = esc_html($options['texto']);
    $fondo = esc_attr($options['fondo']);
    $color = esc_attr($options['color']);

    // Renderizamos CSS y HTML en bloque (Minificado para máxima velocidad)
    ?>
    <style>
        #ov-banner-superior {
            width: 100%;
            background-color: <?php echo $fondo; ?>;
            color: <?php echo $color; ?>;
            overflow: hidden;
            position: relative;
            z-index: 99999;
            box-sizing: border-box
        }

        #ov-banner-superior .ov-banner-track {
            display: flex;
            width: max-content;
            animation: ov-banner-marquee 25s linear infinite
        }

        #ov-banner-superior .ov-banner-content {
            display: block;
            flex-shrink: 0;
            padding: 9px 60px 9px 0;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.4;
            white-space: nowrap;
            box-sizing: border-box
        }

        @keyframes ov-banner-marquee {
            0% {
                transform: translateX(0)
            }

            100% {
                transform: translateX(-50%)
            }
        }

        #ov-banner-superior:hover .ov-banner-track {
            animation-play-state: paused
        }

        @media (prefers-reduced-motion:reduce) {
            #ov-banner-superior .ov-banner-track {
                animation: none;
                width: 100%;
                justify-content: center
            }

            #ov-banner-superior .ov-banner-content {
                white-space: normal;
                text-align: center;
                padding: 8px 20px
            }

            #ov-banner-superior .ov-banner-content:nth-child(2) {
                display: none
            }
        }

        @media (max-width:767px) {
            #ov-banner-superior .ov-banner-content {
                font-size: 12px;
                padding-top: 8px;
                padding-bottom: 8px
            }

            #ov-banner-superior .ov-banner-track {
                animation-duration: 20s
            }
        }
    </style>
    <div id="ov-banner-superior" role="region" aria-label="Información importante">
        <div class="ov-banner-track">
            <span class="ov-banner-content"><?php echo $texto; ?></span>
            <span class="ov-banner-content" aria-hidden="true"><?php echo $texto; ?></span>
        </div>
    </div>
    <?php
}