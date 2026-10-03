<?php

/**
 * One gateway per provider, its options from the environment (.env): a
 * gateway is configured only when every key under "needs" is set.
 *
 * @return array<string, array{factory: string, needs: list<string>, options: array<string, mixed>}>
 */
$env = static fn (string $key, mixed $default = null): mixed => (false !== ($v = getenv($key)) && '' !== $v) ? $v : $default;

return [
    'stripe' => ['factory' => 'stripe', 'needs' => ['STRIPE_API_KEY'], 'options' => ['api_key' => $env('STRIPE_API_KEY'), 'webhook_secret' => $env('STRIPE_WEBHOOK_SECRET'), 'adaptive_pricing' => '1' === $env('STRIPE_ADAPTIVE_PRICING', '0')]],
    'paypal' => ['factory' => 'paypal', 'needs' => ['PAYPAL_CLIENT_ID', 'PAYPAL_SECRET'], 'options' => ['client_id' => $env('PAYPAL_CLIENT_ID'), 'secret' => $env('PAYPAL_SECRET'), 'webhook_id' => $env('PAYPAL_WEBHOOK_ID'), 'sandbox' => '0' !== $env('PAYPAL_SANDBOX', '1')]],
    'shopify' => ['factory' => 'shopify', 'needs' => ['SHOPIFY_SHOP_DOMAIN', 'SHOPIFY_ADMIN_TOKEN'], 'options' => ['shop_domain' => $env('SHOPIFY_SHOP_DOMAIN'), 'admin_token' => $env('SHOPIFY_ADMIN_TOKEN'), 'webhook_secret' => $env('SHOPIFY_WEBHOOK_SECRET'), 'storefront_token' => $env('SHOPIFY_STOREFRONT_TOKEN'), 'api_version' => $env('SHOPIFY_API_VERSION'), 'currency' => $env('SHOPIFY_CURRENCY')]],
    'woocommerce' => ['factory' => 'woocommerce', 'needs' => ['WOOCOMMERCE_URL', 'WOOCOMMERCE_CONSUMER_KEY', 'WOOCOMMERCE_CONSUMER_SECRET'], 'options' => ['url' => $env('WOOCOMMERCE_URL'), 'consumer_key' => $env('WOOCOMMERCE_CONSUMER_KEY'), 'consumer_secret' => $env('WOOCOMMERCE_CONSUMER_SECRET'), 'webhook_secret' => $env('WOOCOMMERCE_WEBHOOK_SECRET'), 'currency' => $env('WOOCOMMERCE_CURRENCY'), 'weight_unit' => $env('WOOCOMMERCE_WEIGHT_UNIT')]],
];
