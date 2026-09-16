<?php
/**
 * Plugin Name: OV - Banner Superior RuruLabs
 * Description: Banner superior configurable desde el administrador de WordPress.
 * Version: 3.0.0
 * Author: RuruLabs
 */

if (!defined('ABSPATH')) {
    exit;
}


/**
 * =========================================================
 * CONFIGURACIÓN POR DEFECTO
 * =========================================================
 */

function ov_banner_default_options()
{

    return array(
        'activo' => 1,

        'texto' => 'ENVÍO GRATIS EN PAÑALES POR COMPRAS DESDE S/ 120.00   •   OFERTAS EXCLUSIVAS EN PACKS x2, x3 y x4   •  ',

        'fondo' => '#0eb8b1',

        'color' => '#ffffff',
    );
}


/**
 * =========================================================
 * OBTENER OPCIONES
 * =========================================================
 */

function ov_banner_get_options()
{

    $defaults = ov_banner_default_options();

    $options = get_option('ov_banner_options', array());

    if (!is_array($options)) {
        $options = array();
    }

    return wp_parse_args($options, $defaults);
}


/**
 * =========================================================
 * MENÚ "RuruLabs"
 * =========================================================
 */

function ov_banner_admin_menu()
{

    /**
     * Menú principal:
     *
     * RuruLabs
     *
     * Icono: megáfono
     */

    add_menu_page(
        'RuruLabs',
        'RuruLabs',
        'manage_options',
        'rurulabs',
        'ov_banner_admin_page',
        'dashicons-megaphone',
        58
    );


    /**
     * Submenú:
     *
     * RuruLabs
     * └── Banner Superior
     */

    add_submenu_page(
        'rurulabs',
        'Banner Superior',
        'Banner Superior',
        'manage_options',
        'rurulabs',
        'ov_banner_admin_page'
    );
}

add_action('admin_menu', 'ov_banner_admin_menu');


/**
 * =========================================================
 * CARGAR COLOR PICKER
 * =========================================================
 */

function ov_banner_admin_assets($hook)
{

    if ('toplevel_page_rurulabs' !== $hook) {
        return;
    }

    wp_enqueue_style('wp-color-picker');

    wp_enqueue_script(
        'wp-color-picker'
    );
}

add_action('admin_enqueue_scripts', 'ov_banner_admin_assets');


/**
 * =========================================================
 * GUARDAR CONFIGURACIÓN
 * =========================================================
 */

function ov_banner_save_settings()
{

    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['ov_banner_save'])) {
        return;
    }

    if (
        !isset($_POST['ov_banner_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(
                wp_unslash($_POST['ov_banner_nonce'])
            ),
            'ov_banner_save_settings'
        )
    ) {
        return;
    }


    /**
     * Estado del banner
     */

    $activo = isset($_POST['activo']) ? 1 : 0;


    /**
     * Texto
     */

    $texto = isset($_POST['texto'])
        ? sanitize_textarea_field(
            wp_unslash($_POST['texto'])
        )
        : '';


    /**
     * Color de fondo
     */

    $fondo = isset($_POST['fondo'])
        ? sanitize_hex_color(
            wp_unslash($_POST['fondo'])
        )
        : '';


    /**
     * Color del texto
     */

    $color = isset($_POST['color'])
        ? sanitize_hex_color(
            wp_unslash($_POST['color'])
        )
        : '';


    /**
     * Si algún color es inválido,
     * utilizar el valor por defecto.
     */

    if (!$fondo) {
        $fondo = '#ff5a00';
    }

    if (!$color) {
        $color = '#ffffff';
    }


    /**
     * Guardar opciones
     */

    $options = array(
        'activo' => $activo,
        'texto' => $texto,
        'fondo' => $fondo,
        'color' => $color,
    );

    update_option(
        'ov_banner_options',
        $options
    );


    /**
     * Mensaje de éxito
     */

    add_settings_error(
        'ov_banner_messages',
        'ov_banner_message',
        'Configuración guardada correctamente.',
        'updated'
    );
}

add_action(
    'admin_init',
    'ov_banner_save_settings'
);


/**
 * =========================================================
 * PÁGINA DE ADMINISTRACIÓN
 * =========================================================
 */

function ov_banner_admin_page()
{

    if (!current_user_can('manage_options')) {
        return;
    }

    $options = ov_banner_get_options();

    settings_errors('ov_banner_messages');

    ?>

    <div class="wrap">

        <h1 style="margin-bottom:20px;">
            📣 Banner Superior
        </h1>


        <div style="
                background:#fff;
                border:1px solid #dcdcde;
                border-radius:8px;
                padding:25px;
                max-width:900px;
            ">

            <form method="post">

                <?php
                wp_nonce_field(
                    'ov_banner_save_settings',
                    'ov_banner_nonce'
                );
                ?>


                <!-- ================================================= -->
                <!-- ACTIVAR / DESACTIVAR -->
                <!-- ================================================= -->

                <div style="
                        display:flex;
                        align-items:center;
                        justify-content:space-between;
                        padding:15px 0 25px 0;
                        border-bottom:1px solid #eee;
                        margin-bottom:25px;
                    ">

                    <div>

                        <strong style="
                                display:block;
                                font-size:16px;
                                margin-bottom:5px;
                            ">
                            Activar banner
                        </strong>

                        <span style="color:#646970;">
                            Activa o desactiva el banner sin eliminar su configuración.
                        </span>

                    </div>


                    <label class="ov-switch" style="
                            position:relative;
                            display:inline-block;
                            width:52px;
                            height:28px;
                        ">

                        <input type="checkbox" name="activo" id="ov_banner_activo" value="1" <?php checked($options['activo'], 1); ?> style="opacity:0;width:0;height:0;">

                        <span class="ov-slider" style="
                                position:absolute;
                                cursor:pointer;
                                top:0;
                                left:0;
                                right:0;
                                bottom:0;
                                background:#ccc;
                                transition:.3s;
                                border-radius:28px;
                            "></span>

                    </label>

                </div>


                <!-- ================================================= -->
                <!-- TEXTO -->
                <!-- ================================================= -->

                <table class="form-table" role="presentation">

                    <tr>

                        <th scope="row">

                            <label for="ov_banner_texto">
                                Texto del banner
                            </label>

                        </th>

                        <td>

                            <textarea name="texto" id="ov_banner_texto" rows="4" class="large-text"
                                placeholder="Escribe el texto que aparecerá en el banner..."><?php echo esc_textarea($options['texto']); ?></textarea>

                            <p class="description">
                                Puedes utilizar • para separar diferentes mensajes.
                            </p>

                        </td>

                    </tr>


                    <!-- ================================================= -->
                    <!-- COLOR FONDO -->
                    <!-- ================================================= -->

                    <tr>

                        <th scope="row">

                            <label for="ov_banner_fondo">
                                Color de fondo
                            </label>

                        </th>

                        <td>

                            <input type="text" name="fondo" id="ov_banner_fondo"
                                value="<?php echo esc_attr($options['fondo']); ?>" class="ov-color-picker">

                        </td>

                    </tr>


                    <!-- ================================================= -->
                    <!-- COLOR TEXTO -->
                    <!-- ================================================= -->

                    <tr>

                        <th scope="row">

                            <label for="ov_banner_color">
                                Color del texto
                            </label>

                        </th>

                        <td>

                            <input type="text" name="color" id="ov_banner_color"
                                value="<?php echo esc_attr($options['color']); ?>" class="ov-color-picker">

                        </td>

                    </tr>

                </table>


                <!-- ================================================= -->
                <!-- VISTA PREVIA -->
                <!-- ================================================= -->

                <h2 style="margin-top:30px;">
                    Vista previa
                </h2>


                <div id="ov-banner-preview" style="
                        width:100%;
                        overflow:hidden;
                        padding:14px 0;
                        border-radius:4px;
                        font-weight:600;
                        font-size:14px;
                        white-space:nowrap;
                        box-sizing:border-box;
                        margin-bottom:25px;
                    ">

                    <div id="ov-banner-preview-text" style="
                            display:inline-block;
                            padding-left:100%;
                        ">
                        <?php
                        echo esc_html($options['texto']);
                        ?>
                    </div>

                </div>


                <?php
                submit_button(
                    'Guardar cambios',
                    'primary',
                    'ov_banner_save'
                );
                ?>

            </form>

        </div>

    </div>


    <style>
        /**
                         * Interruptor
                         */

        .ov-switch input:checked+.ov-slider {
            background: #2271b1 !important;
        }

        .ov-switch input:checked+.ov-slider:before {
            transform: translateX(24px);
        }

        .ov-slider:before {
            content: "";
            position: absolute;
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .25);
        }


        /**
                         * Animación de preview
                         */

        #ov-banner-preview-text {
            animation: ovBannerPreview 10s linear infinite;
        }

        @keyframes ovBannerPreview {

            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-100%);
            }

        }
    </style>


    <script>

        jQuery(document).ready(function ($) {

            /**
             * Color pickers
             */

            $('.ov-color-picker').wpColorPicker({

                change: function (event, ui) {

                    actualizarPreview();

                },

                clear: function () {

                    actualizarPreview();

                }

            });


            /**
             * Actualizar preview
             */

            function actualizarPreview() {

                var texto = $('#ov_banner_texto').val();

                var fondo = $('#ov_banner_fondo').val();

                var color = $('#ov_banner_color').val();


                $('#ov-banner-preview')
                    .css('background-color', fondo)
                    .css('color', color);


                $('#ov-banner-preview-text')
                    .text(texto);

            }


            /**
             * Texto en tiempo real
             */

            $('#ov_banner_texto').on(
                'input',
                function () {

                    actualizarPreview();

                }
            );


            /**
             * Inicializar preview
             */

            actualizarPreview();

        });

    </script>

    <?php
}


/**
 * =========================================================
 * MOSTRAR BANNER EN EL FRONTEND
 * =========================================================
 */

function ov_banner_frontend()
{

    /**
     * No mostrar en administración.
     */

    if (is_admin()) {
        return;
    }


    /**
     * Obtener configuración.
     */

    $options = ov_banner_get_options();


    /**
     * Si está desactivado, no mostrar.
     */

    if (empty($options['activo'])) {
        return;
    }


    /**
     * Si no existe texto, no mostrar.
     */

    if (empty(trim($options['texto']))) {
        return;
    }


    ?>

    <div id="ov-banner-superior" role="region" aria-label="Información importante">

        <div class="ov-banner-track">

            <span class="ov-banner-content">
                <?php echo esc_html($options['texto']); ?>
            </span>

            <span class="ov-banner-content" aria-hidden="true">
                <?php echo esc_html($options['texto']); ?>
            </span>

        </div>

    </div>

    <?php
}

add_action(
    'wp_body_open',
    'ov_banner_frontend',
    5
);


/**
 * =========================================================
 * CSS DEL BANNER
 * =========================================================
 */

function ov_banner_styles()
{

    if (is_admin()) {
        return;
    }

    $options = ov_banner_get_options();

    if (empty($options['activo'])) {
        return;
    }

    if (empty(trim($options['texto']))) {
        return;
    }

    $fondo = esc_attr($options['fondo']);
    $color = esc_attr($options['color']);

    ?>

    <style id="ov-banner-superior-css">
        #ov-banner-superior {

            width: 100%;

            background-color:
                <?php echo $fondo; ?>
            ;

            color:
                <?php echo $color; ?>
            ;

            overflow: hidden;

            position: relative;

            z-index: 99999;

            box-sizing: border-box;

        }


        #ov-banner-superior .ov-banner-track {

            display: flex;

            width: max-content;

            animation:
                ov-banner-marquee 25s linear infinite;

        }


        #ov-banner-superior .ov-banner-content {

            display: block;

            flex-shrink: 0;

            padding:

                9px 60px 9px 0;

            font-size: 13px;

            font-weight: 600;

            line-height: 1.4;

            white-space: nowrap;

            box-sizing: border-box;

        }


        /**
                         * Animación
                         */

        @keyframes ov-banner-marquee {

            0% {

                transform: translateX(0);

            }

            100% {

                transform: translateX(-50%);

            }

        }


        /**
                         * Pausar al pasar el mouse
                         */

        #ov-banner-superior:hover .ov-banner-track {

            animation-play-state: paused;

        }


        /**
                         * Accesibilidad:
                         * respetar usuarios que prefieren
                         * reducir movimiento.
                         */

        @media (prefers-reduced-motion:reduce) {

            #ov-banner-superior .ov-banner-track {

                animation: none;

                width: 100%;

                justify-content: center;

            }

            #ov-banner-superior .ov-banner-content {

                white-space: normal;

                text-align: center;

                padding-right: 20px;

                padding-left: 20px;

            }

            #ov-banner-superior .ov-banner-content:nth-child(2) {

                display: none;

            }

        }


        /**
                         * Mobile
                         */

        @media (max-width:767px) {

            #ov-banner-superior .ov-banner-content {

                font-size: 12px;

                padding-top: 8px;

                padding-bottom: 8px;

            }

            #ov-banner-superior .ov-banner-track {

                animation-duration: 20s;

            }

        }
    </style>

    <?php
}

add_action(
    'wp_head',
    'ov_banner_styles',
    20
);