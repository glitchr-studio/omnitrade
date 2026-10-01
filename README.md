# glitchr/omnitrade

One contract for payment providers and commerce platforms - the Omnibus of trade.

```php
$gateway = $registry->get('card');
$transaction = $gateway->purchase($payment);   // PAID, or PENDING with the page to send the buyer to
$gateway->fetch($transaction->reference);      // where it stands now
$gateway->refund($transaction->reference, Money::of(500, 'EUR'), idempotencyKey: 'refund-42');
$gateway->notify($request->getContent(), $request->headers->all());   // a webhook, checked and read
```

This package holds the contract (`GatewayInterface`, `GatewayFactory`, `Registry`), the models
(`Payment`, `Money`, `Transaction`, `Refund`, `PaymentMethod`, `Notification`, `PlatformOrder`...),
the requests and the Symfony bundle. Each provider is a package of its own:

| Package | Provider |
|---|---|
| `omnitrade/stripe` | Stripe: Checkout sessions, refunds, webhooks (on omnipay/stripe) |
| `omnitrade/paypal` | PayPal: orders, capture, refunds, webhooks (on omnipay/paypal) |
| `omnitrade/shopify` | Shopify: draft orders paid on the invoice page, orders, webhooks (Admin GraphQL) |
| `omnitrade/woocommerce` | WooCommerce: orders and their payment state (REST API v3) |

A provider's factory fills a `Config` - its name, options, API client and actions - and the
gateway runs the actions that support each request, exactly as an Omnibus carrier does. A
provider that does not do something (no authorizations at Shopify) throws
`RequestNotSupportedException`; `supports()` says so beforehand.

What every provider answers with is kept whole in `$raw`: normalization loses nothing.

## Symfony

`Omnitrade\Bridge\Symfony\OmnitradeBundle`: every `omnitrade/*` provider installed registered,
`Omnitrade\Registry` autowired, and each configured gateway injectable by its name.

```yaml
omnitrade:
    gateways:
        card:   { factory: stripe, options: { api_key: '%env(STRIPE_API_KEY)%', webhook_secret: '%env(STRIPE_WEBHOOK_SECRET)%' } }
        paypal: { factory: paypal, options: { client_id: '%env(PAYPAL_CLIENT_ID)%', secret: '%env(PAYPAL_SECRET)%', sandbox: true } }
```

```php
public function __construct(GatewayInterface $card) {}
```

An application's own `GatewayFactoryInterface` is registered too (autoconfigured).

License: LGPL-3.0-or-later.
