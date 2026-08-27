#!/bin/bash

echo "🔄 Configurando Checkout clásico para Ubigeo Perú..."
CHECKOUT_ID=$(wp option get woocommerce_checkout_page_id)
wp post update $CHECKOUT_ID --post_content='[woocommerce_checkout]'

# Opcional: También pasar el carrito al modo clásico si lo deseas
CART_ID=$(wp option get woocommerce_cart_page_id)
wp post update $CART_ID --post_content='[woocommerce_cart]'

echo "💳 Habilitando Métodos de Pago..."
wp eval '
    $bacs = get_option( "woocommerce_bacs_settings", array() );
    $bacs["enabled"] = "yes";
    $bacs["title"] = "Transferencia Bancaria (Yape / Plin)";
    update_option( "woocommerce_bacs_settings", $bacs );
    
    $cod = get_option( "woocommerce_cod_settings", array() );
    $cod["enabled"] = "yes";
    $cod["title"] = "Pago Contra Entrega";
    update_option( "woocommerce_cod_settings", $cod );
' --user=${WP_ADMIN_USER}