<?php
/**
 * Plugin Name: Ruru - Buscador de Google Maps Superior para Ubigeo Perú
 * Description: Agrega un campo buscador de direcciones debajo del correo y autocompleta los campos de Ubigeo Perú hacia abajo.
 * Version: 2.0.0
 * Author: Ruru Lab
 */

if (!defined('ABSPATH')) {
    exit;
}

// =========================================================================
// 1. CREAR EL CAMPO BUSCADOR DEBAJO DEL CORREO
// =========================================================================
add_filter('woocommerce_checkout_fields', 'ruru_insert_search_address_field', 99);
function ruru_insert_search_address_field($fields)
{
    // Buscar la prioridad actual del campo de correo electrónico para posicionarnos justo debajo
    $email_priority = isset($fields['billing']['billing_email']['priority']) ? (int) $fields['billing']['billing_email']['priority'] : 110;

    // Crear el nuevo campo buscador en facturación
    $fields['billing']['billing_search_address'] = array(
        'type' => 'text',
        'label' => 'Buscar tu dirección',
        'placeholder' => 'Ej: Avenida San Felipe 164...',
        'required' => false, // Es solo un campo de búsqueda auxiliar
        'class' => array('form-row-wide', 'ruru-map-search-field'),
        'clear' => true,
        'priority' => 1, // Primero en el formulario (antes de Nombre/Apellidos)
    );

    // Opcional: Hacer lo mismo en la sección de envío si está activa
    if (isset($fields['shipping'])) {
        $fields['shipping']['shipping_search_address'] = array(
            'type' => 'text',
            'label' => 'Buscar dirección de envío',
            'placeholder' => 'Ej: Avenida San Felipe 164...',
            'required' => false,
            'class' => array('form-row-wide', 'ruru-map-search-field'),
            'clear' => true,
            'priority' => 5, // Al principio del formulario de envío
        );
    }

    return $fields;
}

// =========================================================================
// 2. ENCOLAR GOOGLE PLACES API Y EL SCRIPT DE AUTOCOMPLETADO
// =========================================================================
add_action('wp_enqueue_scripts', 'ruru_enqueue_checkout_autocomplete_v2', 99);
function ruru_enqueue_checkout_autocomplete_v2()
{
    if (!function_exists('is_checkout') || !is_checkout() || is_wc_endpoint_url('order-pay') || is_wc_endpoint_url('order-received')) {
        return;
    }

    // IMPORTANTE: Coloca tu API KEY aquí
    $google_api_key = 'AIzaSyAM37iJTRcIoSAxESlDzB2DxlNJWKasW5U';

    wp_enqueue_script(
        'google-places-api',
        'https://maps.googleapis.com/maps/api/js?key=' . $google_api_key . '&libraries=places&callback=initRuruAutocomplete',
        array('jquery'),
        null,
        true
    );

    wp_add_inline_script('google-places-api', ruru_get_autocomplete_js_script_v2(), 'before');
}

// =========================================================================
// 3. LÓGICA JAVASCRIPT (REPARTE LOS DATOS HACIA ABAJO CON JQUERY)
// =========================================================================
function ruru_get_autocomplete_js_script_v2()
{
    ob_start();
    ?>
        window.initRuruAutocomplete = function() {
        const searchInputs = ['billing_search_address', 'shipping_search_address'];
        searchInputs.forEach(id => {
                const searchInput = document.getElementById(id);
            if (!searchInput) return;
            searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') e.preventDefault();
            });
            const autocomplete = new google.maps.places.Autocomplete(searchInput, {
                    componentRestrictions: { country: 'pe' },
                fields: ['address_components', 'formatted_address', 'name', 'place_id'],
                types: ['address']
            });
            autocomplete.addListener('place_changed', function() {
                    const place = autocomplete.getPlace();
                if (!place || !place.place_id) return;

                // Usamos PlacesService.getDetails para obtener TODOS los componentes
                // (incluyendo sublocality_level_2 que es el distrito real en Lima)
                const placesService = new google.maps.places.PlacesService(document.createElement('div'));
                placesService.getDetails({
                    placeId: place.place_id,
                    fields: ['address_components', 'formatted_address']
                }, function(detail, status) {
                    if (status !== google.maps.places.PlacesServiceStatus.OK || !detail) return;

                    let route = '', street_number = '';
                    let distrito = '', provincia = '', departamento = '';

                    detail.address_components.forEach(function(component) {
                        const types = component.types;
                        if (types.includes('route'))         route         = component.long_name;
                        if (types.includes('street_number')) street_number = component.long_name;

                        // Prioridad para el distrito (de más específico a más general):
                        // sublocality_level_2 → sublocality_level_1 → administrative_area_level_3 → locality
                        if (types.includes('sublocality_level_2')) {
                            distrito = component.long_name;
                        } else if (!distrito && types.includes('sublocality_level_1')) {
                            distrito = component.long_name;
                        } else if (!distrito && types.includes('administrative_area_level_3')) {
                            distrito = component.long_name;
                        } else if (!distrito && types.includes('locality')) {
                            distrito = component.long_name;
                        }

                        if (types.includes('administrative_area_level_2')) provincia   = component.long_name;
                        if (types.includes('administrative_area_level_1')) departamento = component.long_name;
                    });

                    // Normalizar textos para que coincidan con el plugin Ubigeo
                    if (provincia)    provincia    = provincia.replace(/Provincia de/i, '').replace(/Province/i, '').trim();
                    if (departamento) departamento = departamento.replace(/Department/i, '').replace(/Regi[oó]n/i, '').trim();

                    // Normalización obligatoria para Lima y Callao
                    if (departamento.includes('Metropolitana de Lima') || departamento === 'Lima') departamento = 'Lima';
                    if (departamento.includes('Callao')) departamento = 'Callao';

                    // Si Google omite la provincia pero sabemos el departamento
                    if (departamento === 'Lima'   && !provincia) provincia = 'Lima';
                    if (departamento === 'Callao' && !provincia) provincia = 'Callao';

                    const type = id.split('_')[0];

                    // 1. Llenar el campo "Dirección de la calle"
                    const realAddressInput = jQuery('#' + type + '_address_1');
                    if (realAddressInput.length) {
                        realAddressInput.val((route + ' ' + street_number).trim()).trigger('change');
                    }

                    // 2. Llenar los combos en cascada (Departamento → Provincia → Distrito)
                    ruruFillUbigeoFields(type, departamento, provincia, distrito);
                });
            });
            });
    }

    // Mover el campo de búsqueda al inicio del formulario (antes de Nombre/Apellidos)
    // Se hace vía DOM porque el tema/plugin Ubigeo ignora la prioridad PHP.
    (function moveSearchFieldToTop() {
        var move = function() {
            var searchField = document.getElementById('billing_search_address_field');
            var form = document.querySelector('.woocommerce-billing-fields__field-wrapper');
            if (!form) form = document.querySelector('.woocommerce-billing-fields');
            if (searchField && form) {
                form.insertBefore(searchField, form.firstChild);
            }
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', move);
        } else {
            move();
        }
    })();

function ruruFillUbigeoFields(type, depText, provText, distText) {
        const normalize = str => str ? str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().trim() : '';

        /**
         * Busca el valor dentro de las options del select cuyo texto coincida
         * (normalizado) con `text`. Devuelve el value o null si no lo encuentra.
         */
        const findOption = ($select, text) => {
            const target = normalize(text);
            let found = null;
            $select.find('option').each(function() {
                const optText = normalize(jQuery(this).text());
                if (optText === target || optText.includes(target) || target.includes(optText)) {
                    found = jQuery(this).val();
                    return false;
                }
            });
            return found;
        };

        /**
         * Aplica la selección al <select> nativo y notifica a Select2 / SelectWoo.
         */
        const applyValue = ($select, val) => {
            if ($select.val() === val) return;
            $select.val(val);
            // Evento nativo → activa el AJAX del plugin Ubigeo
            $select.trigger('change');
            // Select2 / SelectWoo: notificamos también con su propio evento
            if ($select.data('select2')) {
                $select.trigger('change.select2');
            }
        };

        /**
         * Intenta seleccionar una opción en `fieldId` que coincida con `text`.
         * Si el select todavía no tiene las opciones (porque están cargando por AJAX),
         * usa un MutationObserver para esperar a que se pueble y luego selecciona.
         * Cuando termina (éxito o timeout) llama a `callback`.
         */
        const selectOption = (fieldId, text, callback) => {
            const $select = jQuery('#' + fieldId);
            if (!$select.length || !text) { if (callback) callback(); return; }

            // ── Intento inmediato ────────────────────────────────────────────
            const immediate = findOption($select, text);
            if (immediate !== null) {
                applyValue($select, immediate);
                // Esperamos a que el AJAX hijo cargue antes de llamar al callback
                if (callback) setTimeout(callback, 1200);
                return;
            }

            // ── Las opciones aún no están: esperamos con MutationObserver ───
            const selectEl = $select[0];
            let settled = false;
            const TIMEOUT_MS = 6000; // máximo 6 segundos

            const finish = (val) => {
                if (settled) return;
                settled = true;
                observer.disconnect();
                clearTimeout(giveUp);
                if (val !== null) {
                    applyValue($select, val);
                    if (callback) setTimeout(callback, 1200);
                } else {
                    // No se encontró la opción tras esperar: continuamos igual
                    if (callback) setTimeout(callback, 200);
                }
            };

            const observer = new MutationObserver(() => {
                const val = findOption($select, text);
                if (val !== null) finish(val);
            });

            observer.observe(selectEl, { childList: true, subtree: true });

            const giveUp = setTimeout(() => finish(null), TIMEOUT_MS);
        };

        // ── IDs de los campos del plugin Ubigeo Perú ──────────────────────────
        // El plugin usa billing_departamento / billing_provincia / billing_distrito.
        // Si no existen, caemos a los IDs estándar de WooCommerce.
        const prefix = type;

        const depId  = jQuery('#' + prefix + '_departamento').length ? prefix + '_departamento' : prefix + '_state';
        const provId = jQuery('#' + prefix + '_provincia').length    ? prefix + '_provincia'    : prefix + '_city';
        const distId = jQuery('#' + prefix + '_distrito').length     ? prefix + '_distrito'
                        : (jQuery('#' + prefix + '_address_2').length ? prefix + '_address_2' : '');

        // ── Cascada: Departamento → (espera AJAX) → Provincia → (espera AJAX) → Distrito
        selectOption(depId, depText, () => {
            selectOption(provId, provText, () => {
                if (distId) selectOption(distId, distText);
            });
        });
    }
        <?php
        return ob_get_clean();
}

// =========================================================================
// 4. ESTILOS CSS (Añade el ícono de mapa para que se vea como Google)
// =========================================================================
add_action('wp_head', 'ruru_search_address_styles');
function ruru_search_address_styles()
{
    if (function_exists('is_checkout') && is_checkout()) {
        echo '<style>
        /* Añadir el pin rojo de ubicación dentro del input */
        #billing_search_address_field input, #shipping_search_address_field input {
            background-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' width=\'20\' fill=\'%23ea4335\'%3E%3Cpath d=\'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z\'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 12px center;
            padding-left: 40px !important;
            border: 1px solid #aaa !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            font-weight: 500;
        }
        /* Resaltar el contenedor para que destaque */
        .ruru-map-search-field { margin-bottom: 25px !important; margin-top: 10px !important; }
        .ruru-map-search-field label { font-weight: 700; color: #111; }
        </style>';
    }
}