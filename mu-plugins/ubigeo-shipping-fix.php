<?php

/*
Plugin Name: Fix Envíos - Integración CSV
Description: Extrae el distrito real y calcula tarifas basadas en un archivo CSV.
Version: 1.1.0
*/


/**
 * ============================================================
 * 1. INTERCEPTAR AJAX + SELECCIONAR LIMA POR DEFECTO
 * ============================================================
 */

add_action('wp_footer', function () {

    if (is_checkout() && !is_wc_endpoint_url()) {
        ?>

        <script>

            jQuery(document).ready(function ($) {

                /**
                 * ------------------------------------------------
                 * SELECCIONAR UNA OPCIÓN POR VALUE O POR TEXTO
                 * ------------------------------------------------
                 */
                function seleccionarOpcion(select, textoBuscado) {

                    var encontrado = false;

                    $(select).find('option').each(function () {

                        var value = ($(this).val() || '').toString().trim().toUpperCase();
                        var texto = ($(this).text() || '').toString().trim().toUpperCase();

                        if (
                            value === textoBuscado.toUpperCase() ||
                            texto === textoBuscado.toUpperCase()
                        ) {

                            $(select).val($(this).val());

                            encontrado = true;

                            return false;
                        }

                    });

                    return encontrado;
                }


                /**
                 * ------------------------------------------------
                 * CONFIGURAR LIMA COMO UBICACIÓN POR DEFECTO
                 * ------------------------------------------------
                 */
                function establecerLimaPorDefecto() {

                    var departamento = $('#billing_departamento');

                    /*
                     * Si el campo no existe todavía, salimos.
                     */
                    if (departamento.length === 0) {
                        return;
                    }


                    /*
                     * --------------------------------------------
                     * 1. DEPARTAMENTO = LIMA
                     * --------------------------------------------
                     */

                    var departamentoActual = departamento.val();

                    /*
                     * Solo establecemos LIMA si todavía
                     * no hay un departamento seleccionado.
                     */
                    if (
                        !departamentoActual ||
                        departamentoActual === '' ||
                        departamentoActual === '0'
                    ) {

                        if (seleccionarOpcion(departamento, 'LIMA')) {

                            /*
                             * Disparamos change para que el sistema
                             * de Ubigeo cargue las provincias de Lima.
                             */
                            departamento.trigger('change');

                        }

                    }


                    /*
                     * --------------------------------------------
                     * 2. PROVINCIA = LIMA
                     * --------------------------------------------
                     *
                     * La provincia puede tardar unos milisegundos
                     * en cargarse después de seleccionar Lima.
                     *
                     * Por eso hacemos varios intentos.
                     */

                    var intentos = 0;
                    var maxIntentos = 50;

                    var intervaloProvincia = setInterval(function () {

                        intentos++;

                        var provincia = $('#billing_provincia');

                        /*
                         * Si no existe billing_provincia,
                         * usamos billing_city como fallback.
                         */
                        if (provincia.length === 0) {
                            provincia = $('#billing_city');
                        }

                        if (provincia.length > 0) {

                            var provinciaActual = provincia.val();

                            /*
                             * Solo seleccionamos LIMA si está vacía.
                             */
                            if (
                                !provinciaActual ||
                                provinciaActual === '' ||
                                provinciaActual === '0'
                            ) {

                                if (seleccionarOpcion(provincia, 'LIMA')) {

                                    provincia.trigger('change');

                                    clearInterval(intervaloProvincia);

                                    /*
                                     * Actualizar checkout para que
                                     * se recalculen los costos de envío.
                                     */
                                    $('body').trigger('update_checkout');

                                }

                            } else {

                                /*
                                 * Ya existe una provincia seleccionada.
                                 * No la modificamos.
                                 */
                                clearInterval(intervaloProvincia);

                            }

                        }


                        /*
                         * Evitar que el intervalo quede ejecutándose
                         * indefinidamente.
                         */
                        if (intentos >= maxIntentos) {
                            clearInterval(intervaloProvincia);
                        }

                    }, 100);

                }


                /**
                 * ------------------------------------------------
                 * EJECUTAR AL CARGAR EL CHECKOUT
                 * ------------------------------------------------
                 */
                setTimeout(function () {

                    establecerLimaPorDefecto();

                }, 500);


                /**
                 * ------------------------------------------------
                 * VOLVER A INTENTAR DESPUÉS DE ACTUALIZACIONES
                 * DE WOOCOMMERCE
                 * ------------------------------------------------
                 */
                $(document.body).on('updated_checkout', function () {

                    /*
                     * Pequeña espera porque algunos plugins
                     * reconstruyen los selects después de
                     * actualizar WooCommerce.
                     */
                    setTimeout(function () {

                        establecerLimaPorDefecto();

                    }, 300);

                });


                /**
                 * =================================================
                 * INTERCEPTAR AJAX DE UPDATE ORDER REVIEW
                 * =================================================
                 */

                $.ajaxSetup({

                    beforeSend: function (jqXHR, settings) {

                        if (
                            settings.url &&
                            settings.url.indexOf('wc-ajax=update_order_review') !== -1
                        ) {

                            /**
                             * ----------------------------------------
                             * DISTRITO
                             * ----------------------------------------
                             */

                            var selectDistrito = $('#billing_distrito');

                            if (
                                selectDistrito.length > 0 &&
                                selectDistrito.is('select')
                            ) {

                                var nombreDistrito =
                                    selectDistrito.find('option:selected').text();

                                if (
                                    nombreDistrito &&
                                    nombreDistrito.toLowerCase().indexOf('seleccione') === -1
                                ) {

                                    settings.data +=
                                        '&real_distrito=' +
                                        encodeURIComponent(
                                            nombreDistrito.trim()
                                        );
                                }

                            }


                            /**
                             * ----------------------------------------
                             * PROVINCIA
                             * ----------------------------------------
                             */

                            var selectProvincia = $('#billing_provincia');

                            if (selectProvincia.length === 0) {
                                selectProvincia = $('#billing_city');
                            }

                            if (
                                selectProvincia.length > 0 &&
                                selectProvincia.is('select')
                            ) {

                                var nombreProvincia =
                                    selectProvincia.find('option:selected').text();

                                if (
                                    nombreProvincia &&
                                    nombreProvincia.toLowerCase().indexOf('seleccione') === -1
                                ) {

                                    settings.data +=
                                        '&real_provincia=' +
                                        encodeURIComponent(
                                            nombreProvincia.trim()
                                        );
                                }

                            }

                        }

                    }

                });


                /**
                 * =================================================
                 * ACTUALIZAR ENVÍO CUANDO CAMBIA EL UBIGEO
                 * =================================================
                 */

                $(document).on(
                    'change',
                    'select[name^="billing_"], select.ubigeo-peru',
                    function () {

                        $('body').trigger('update_checkout');

                    }
                );

            });

        </script>

        <?php
    }

});


/**
 * ============================================================
 * 2. LEER LOS DATOS INTERCEPTADOS
 * ============================================================
 */

add_filter(
    'woocommerce_cart_shipping_packages',
    'ubigeo_forzar_recalculo_distrito',
    99
);

function ubigeo_forzar_recalculo_distrito($packages)
{

    if (isset($_POST['post_data'])) {

        parse_str(
            $_POST['post_data'],
            $post_data
        );


        /**
         * DISTRITO
         */
        if (
            isset($_POST['real_distrito']) &&
            !empty($_POST['real_distrito'])
        ) {

            $packages[0]['destination']['distrito'] =
                sanitize_text_field(
                    wp_unslash($_POST['real_distrito'])
                );

        } elseif (
            !empty($post_data['billing_distrito'])
        ) {

            $packages[0]['destination']['distrito'] =
                $post_data['billing_distrito'];

        }


        /**
         * PROVINCIA
         */
        if (
            isset($_POST['real_provincia']) &&
            !empty($_POST['real_provincia'])
        ) {

            $packages[0]['destination']['city'] =
                sanitize_text_field(
                    wp_unslash($_POST['real_provincia'])
                );

        } elseif (
            !empty($post_data['billing_provincia'])
        ) {

            $packages[0]['destination']['city'] =
                $post_data['billing_provincia'];

        }


        /**
         * DEPARTAMENTO
         */
        if (
            !empty($post_data['billing_departamento'])
        ) {

            $packages[0]['destination']['state'] =
                $post_data['billing_departamento'];

        }

    }

    return $packages;
}


/**
 * ============================================================
 * 3. LÓGICA CON CSV DINÁMICO
 * ============================================================
 */

add_filter(
    'woocommerce_package_rates',
    'ubigeo_tarifas_dinamicas',
    99,
    2
);

function ubigeo_tarifas_dinamicas($rates, $package)
{

    $distrito = isset(
        $package['destination']['distrito']
    )
        ? mb_strtoupper(
            trim(
                $package['destination']['distrito']
            ),
            'UTF-8'
        )
        : '';


    $provincia = isset(
        $package['destination']['city']
    )
        ? mb_strtoupper(
            trim(
                $package['destination']['city']
            ),
            'UTF-8'
        )
        : '';


    $departamento = isset(
        $package['destination']['state']
    )
        ? mb_strtoupper(
            trim(
                $package['destination']['state']
            ),
            'UTF-8'
        )
        : '';


    /**
     * Cargar tarifas del CSV guardadas en BD
     */
    $custom_rates = get_option(
        'wc_custom_shipping_rates',
        array()
    );


    /**
     * Detectar Lima
     */
    $es_lima_departamento = in_array(
        $departamento,
        array(
            'LIMA',
            'LIM',
            'LMA',
            '15',
            'PE:LMA',
            'PE:LIM'
        )
    );


    $es_lima_metropolitana = false;


    if ($es_lima_departamento) {

        if (
            $provincia !== '' &&
            !in_array(
                $provincia,
                array(
                    'LIMA',
                    'LIM',
                    'PROVINCIA DE LIMA'
                )
            )
        ) {

            $es_lima_metropolitana = false;

        } else {

            $es_lima_metropolitana = true;

        }

    }


    /**
     * Si el distrito está en nuestro CSV,
     * asumimos que es Lima Metropolitana
     */
    if (isset($custom_rates[$distrito])) {

        $es_lima_metropolitana = true;

    }


    $cart_total =
        WC()->cart->get_displayed_subtotal();


    /**
     * Filtrar métodos de envío
     */
    foreach ($rates as $rate_key => $rate) {

        $label_lower =
            strtolower($rate->label);


        $es_shalom =
            (
                strpos(
                    $label_lower,
                    'provincia'
                ) !== false
                ||
                strpos(
                    $label_lower,
                    'destino'
                ) !== false
            );


        /**
         * LIMA METROPOLITANA
         */
        if ($es_lima_metropolitana) {

            /*
             * Ocultar envío por provincia
             */
            if ($es_shalom) {

                unset($rates[$rate_key]);

            } else {

                if (
                    isset(
                    $custom_rates[$distrito]
                )
                ) {

                    $tarifa =
                        $custom_rates[$distrito];


                    /**
                     * Envío gratis
                     */
                    if (
                        $tarifa['gratis'] > 0 &&
                        $cart_total >= $tarifa['gratis']
                    ) {

                        $rates[$rate_key]->cost = 0;

                        $rates[$rate_key]->label =
                            'Envío Gratis a ' .
                            ucwords(
                                strtolower(
                                    $distrito
                                )
                            );

                    } else {

                        /**
                         * Tarifa del CSV
                         */
                        $rates[$rate_key]->cost =
                            $tarifa['costo'];

                        $rates[$rate_key]->label =
                            'Envío a ' .
                            ucwords(
                                strtolower(
                                    $distrito
                                )
                            );

                    }

                } else {

                    /**
                     * Fallback
                     */
                    $rates[$rate_key]->cost =
                        15.00;

                    $rates[$rate_key]->label =
                        'Envío Local';

                }

            }


            /**
             * PROVINCIAS
             */
        } else {

            if (!$es_shalom) {

                unset(
                    $rates[$rate_key]
                );

            }

        }

    }


    return $rates;

}