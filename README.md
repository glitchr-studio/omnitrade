# glitchr/omnitrade

One contract for payment providers and commerce platforms - the Omnibus of trade.

```php
$gateway = $registry->get('card');
$transaction = $gateway->purchase($payment);   // PAID, or PENDING with the page to send the buyer to
$gateway->fetch($transaction->reference);      // where it stands now
$gateway->refund($transaction->reference, Money::of(500, 'EUR'), idempotencyKey: 'refund-42');
$gateway->notify($request->getContent(), $request->headers->all());   // a webhook, checked and read

$page = $gateway->fetchProducts(updatedSince: $lastSync);   // the platform's catalogue, page by page
$gateway->fetchProduct('https://cave.example/products/margaux-2019');   // by id or by its page
$gateway->fetchInventory();                                  // stock levels, where the platform counts them
```

The catalogue (`Product`, `ProductVariant`, `Offer`, `Stock`, `Media`, `Option`, `Merchant`)
is described in [docs/catalogue.md](docs/catalogue.md).

This package holds the contract (`GatewayInterface`, `GatewayFactory`, `Registry`), the models
(`Payment`, `Money`, `Transaction`, `Refund`, `PaymentMethod`, `Notification`, `PlatformOrder`,
`Product`, `ProductVariant`, `Offer`, `Stock`...),
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

## Docker: every provider with your test keys

`docker/` runs this package with every `omnitrade/*` provider installed - from GitHub, or from the
checkouts beside this one when `OMNITRADE_PLUGINS=../..` is set - and a console that exercises
them with the keys in `docker/.env` (copy `.env.dist`; `docker compose run --rm omnitrade gateways`
says which providers are configured and what each one does):

```sh
cd docker && cp .env.dist .env
docker compose run --rm omnitrade gateways
docker compose run --rm omnitrade purchase stripe --amount 1990      # prints the Checkout page to open
docker compose run --rm omnitrade fetch stripe cs_test_...
docker compose run --rm omnitrade refund stripe cs_test_... --amount 500
docker compose run --rm omnitrade notify stripe -H 'Stripe-Signature: t=...,v1=...' < event.json
docker compose run --rm omnitrade test                               # every package's tests
```

License: LGPL-3.0-or-later.
