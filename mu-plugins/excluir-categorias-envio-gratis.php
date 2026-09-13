<?php
/**
 * Plugin Name: Excluir Categorías Envío Custom
 * Description: Intercepta las tarifas generadas por el CSV para excluir categorías del cálculo de envío gratis.
 * Version: 2.1
 * Promt: Crea un plugin que me permita excluir categorías del cálculo de envío gratis. El plugin debe interceptar las tarifas generadas por el plugin "envio personalizado por zonas" y excluirlas del cálculo de envío gratis, esto solo debe aplicar para la categorias "nutricion-adultos" y "nutricion-ninos".
 */

if (!defined('ABSPATH')) {
    exit;
}

// Usamos prioridad 999 para asegurarnos de que se ejecute DESPUÉS de que tu plugin asigne sus tarifas
add_filter('woocommerce_package_rates', 'forzar_pago_envio_custom_csv', 999, 2);

function forzar_pago_envio_custom_csv($rates, $package)
{
    $categorias_excluidas = array('nutricion-adultos', 'nutricion-ninos');

    // 1. Calcular subtotal solo de productos VÁLIDOS
    $subtotal_elegible = 0;
    foreach ($package['contents'] as $cart_item) {
        $product_id = $cart_item['product_id'];
        if (!has_term($categorias_excluidas, 'product_cat', $product_id)) {
            $subtotal_elegible += $cart_item['line_subtotal'];
        }
    }

    // 2. Obtener el distrito destino actual
    // En los plugins peruanos, el distrito suele mapearse al campo 'city' o 'address_2'
    $distrito_destino = !empty($package['destination']['city']) ? $package['destination']['city'] : '';

    if (empty($distrito_destino) && !empty($package['destination']['address_2'])) {
        $distrito_destino = $package['destination']['address_2'];
    }

    if (empty($distrito_destino)) {
        return $rates;
    }

    // Formatear igual que en tu importador (Mayúsculas)
    $distrito_destino = mb_strtoupper(trim($distrito_destino), 'UTF-8');

    // 3. Consultar tu base de datos de tarifas custom (generada por tu CSV)
    $custom_rates = get_option('wc_custom_shipping_rates', array());

    if (isset($custom_rates[$distrito_destino])) {
        $monto_minimo_csv = floatval($custom_rates[$distrito_destino]['gratis']);
        $costo_regular_csv = floatval($custom_rates[$distrito_destino]['costo']);

        // 4. Si el subtotal elegible NO alcanza el monto mínimo del distrito...
        if ($subtotal_elegible < $monto_minimo_csv) {
            foreach ($rates as $rate_key => $rate) {
                // Identificar la tarifa generada (si cuesta 0 o lleva "Gratis" en el nombre)
                // Evitamos tocar la recogida local por seguridad
                if (($rate->cost == 0 || stripos($rate->label, 'Gratis') !== false) && strpos($rate->method_id, 'local_pickup') === false) {

                    // Revertimos la tarifa a su costo regular
                    $rates[$rate_key]->cost = $costo_regular_csv;

                    // Cambiamos el título para quitar la palabra "Gratis"
                    $rates[$rate_key]->label = 'Envío a ' . ucwords(mb_strtolower($distrito_destino, 'UTF-8'));

                    // Recalcular impuestos si tu tienda cobra IGV sobre el envío
                    if (class_exists('WC_Tax')) {
                        $rates[$rate_key]->taxes = WC_Tax::calc_shipping_tax($costo_regular_csv, WC_Tax::get_shipping_tax_rates());
                    }
                }
            }
        }
    }

    return $rates;
}