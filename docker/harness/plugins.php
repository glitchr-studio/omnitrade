<?php

/**
 * Every provider package: its slug (omnitrade/<slug>, github.com/glitchr-studio/omnitrade-<slug>)
 * and its factory class.
 */
return [
    'stripe' => ['Omnitrade\\Stripe\\Tests\\', 'Omnitrade\\Stripe\\StripeGatewayFactory'],
    'paypal' => ['Omnitrade\\PayPal\\Tests\\', 'Omnitrade\\PayPal\\PayPalGatewayFactory'],
    'shopify' => ['Omnitrade\\Shopify\\Tests\\', 'Omnitrade\\Shopify\\ShopifyGatewayFactory'],
    'woocommerce' => ['Omnitrade\\WooCommerce\\Tests\\', 'Omnitrade\\WooCommerce\\WooCommerceGatewayFactory'],
];
