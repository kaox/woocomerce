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
        'priority' => $email_priority + 5, // Se ubica inmediatamente después del correo
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
                fields: ['address_components', 'formatted_address', 'name'],
                types: ['address']
            });
            autocomplete.addListener('place_changed', function() {
                    const place = autocomplete.getPlace();
                if (!place.address_components) return;
                let route = '', street_number = '';
                    let distrito = '', provincia = '', departamento = '';
                // Extraer datos de Google Maps
                    place.address_components.forEach(component => {
                    const types = component.types;
                    if (types.includes('route')) route = component.long_name;
                    if (types.includes('street_number')) street_number = component.long_name;
                
                        if (types.includes('locality') || types.includes('sublocality_level_1') || types.includes('administrative_area_level_3')) {
                        if (!distrito) distrito = component.long_name;
                    }
                    if (types.includes('administrative_area_level_2')) {
                        provincia = component.long_name;
                    }
                    if (types.includes('administrative_area_level_1')) {
                        departamento = component.long_name;
                    }
                });
                // Limpiar textos para que coincidan con el Plugin
                    if(provincia) provincia = provincia.replace(/Provincia de/i, '').replace(/Province/i, '').trim();
                if(departamento) departamento = departamento.replace(/Department/i, '').replace(/Región/i, '').replace(/Region/i, '').trim();
                    // Normalización obligatoria para Lima y Callao
                if (departamento.includes('Metropolitana de Lima') || departamento === 'Lima') { departamento = 'Lima'; }
                if (departamento.includes('Callao')) { departamento = 'Callao'; }
                
                // Si Google omite la provincia pero sabemos el departamento
                    if (departamento === 'Lima' && !provincia) provincia = 'Lima';
                if (departamento === 'Callao' && !provincia) provincia = 'Callao';
                const type = id.split('_')[0]; 
                // 1. LLENAR EL CAMPO REAL "DIRECCIÓN DE LA CALLE"
                    const realAddressInput = jQuery('#' + type + '_address_1');
                if(realAddressInput.length) {
                    realAddressInput.val((route + ' ' + street_number).trim()).trigger('change');
                }
                    // 2. LLENAR LOS COMBOS DESPLEGABLES EN CASCADA (AJAX)
                ruruFillUbigeoFields(type, departamento, provincia, distrito);
            });
            });
    }
    function ruruFillUbigeoFields(type, depText, provText, distText) {
        const normalize = str => str ? str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toUpperCase().trim() : '';
        // Función recursiva con jQuery para asegurar compatibilidad con SelectWoo y AJAX
            const selectOption = (fieldId, text, callback) => {
            const $select = jQuery('#' + fieldId);
            if (!$select.length || !text) {
                if (callback) callback();
                return;
            }

            let attempts = 0;
            let targetText = normalize(text);
            const trySelect = setInterval(() => {
                attempts++;
                let found = false;
                let valToSelect = null;
                // Buscar la opción correcta dentro del select
                $select.find('option').each(function() {
                    let optText = normalize(jQuery(this).text());
                    if (optText === targetText || optText.includes(targetText) || targetText.includes(optText)) {
                            valToSelect = jQuery(this).val();
                        found = true;
                        return false; // rompe el bucle each
                    }
                });
                if (found && valToSelect) {
                    clearInterval(trySelect);
                    
                        // Si el valor actual es diferente, lo cambiamos
                        if ($select.val() !== valToSelect) {
                            $select.val(valToSelect).trigger('change'); // Dispara el AJAX de WooCommerce/Ubigeo
                        }
                    
                        // Darle 800ms al servidor para que el plugin de Ubigeo traiga las opciones hijas vía AJAX
                        if (callback) setTimeout(callback, 800); 
                    
                    } else if (attempts > 20) {
                        // Timeout después de 4 segundos (20 intentos x 200ms) para no crear bucles infinitos
                        clearInterval(trySelect);
                        if (callback) setTimeout(callback, 200);
                    }
                }, 200);
            };

            // Identificadores base (El plugin Ubigeo suele usar address_2 o un ID propio para el distrito)
            const stateId = type + '_state';
            const cityId = type + '_city';
        
            // Detección inteligente del campo de distrito
            let distId = type + '_address_2';
            if (jQuery('#' + type + '_distrito').length) {
                distId = type + '_distrito';
            }

            // Ejecutar en cascada rigurosa: 1. Dpto -> (espera) -> 2. Prov -> (espera) -> 3. Dist
            selectOption(stateId, depText, () => {
                selectOption(cityId, provText, () => {
                    selectOption(distId, distText);
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