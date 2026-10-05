# The Docker harness

`docker/` runs this package with every `omnitrade/*` provider installed - from GitHub (branch
1.x), or from the checkouts beside this one when `OMNITRADE_PLUGINS=../..` is set in `docker/.env`
- and a console that exercises them with the test keys in `docker/.env`.

```sh
cd docker && cp .env.dist .env       # your providers' test keys, when you have them
docker compose run --rm omnitrade gateways
```

| Command | |
|---|---|
| `gateways` | the providers installed and configured, what each does |
| `purchase <gateway>` | a payment: its page to open, or its state (`--amount`) |
| `fetch <gateway> <reference>` | where a payment stands |
| `refund <gateway> <reference>` | a refund (`--amount`) |
| `notify <gateway>` | a webhook read from stdin, checked against its signature (`-H`) |
| `methods`, `authorize`, `capture`, `void`, `order`, `link`, `account`, `subscribe`, `subscription` | the rest of the contract, one command each (`--help` says what each takes) |
| `catalogue <gateway>` | products, variants, prices, stock (`--since`, `--query`, `--cursor`, `--limit`, `--product`, `--inventory`) |
| `bare` | plain PHP: the registry built by hand, a product page read, a payment asked, what PHP loaded |
| `test` | every package's tests |

```sh
docker compose run --rm omnitrade purchase stripe --amount 1990      # prints the Checkout page to open
docker compose run --rm omnitrade fetch stripe cs_test_...
docker compose run --rm omnitrade notify stripe -H 'Stripe-Signature: t=...,v1=...' < event.json
docker compose run --rm omnitrade catalogue shopify --since='-1 day' --limit=20
```

## Bare: no bundle, no container

The console above is a `symfony/console` application over a registry built by hand; `bare` is
less still - one PHP script, `docker/harness/bin/bare`, that requires the autoloader and nothing
else. It builds the `Registry` from the provider packages installed and asks each gateway that
can be built what it does. With `--recorded`, on the answers kept in `docker/harness/recorded/`
and without a call, it reads a product from its page's own markup through `omnitrade/web` and
asks PayPal for a payment of 12.50 EUR - an order, and the page to send the buyer to. Without
`--recorded` the gateways are those of `.env`, the page read is the one at `HARNESS_PRODUCT_URL`
when set, and nothing is bought. Then it lists what PHP loaded and exits 1 if a class of a
framework is among it (`Symfony\Component\DependencyInjection`, `Config`, `HttpKernel`,
`HttpFoundation`, a bundle, a bridge, Doctrine, Twig):

```
$ docker compose run --rm omnitrade bare --recorded
Omnitrade in bare PHP: the registry built by hand, no bundle, no container.

  web          product affiliate
  paypal       purchase authorize capture refund fetch methods notify

omnitrade/web, the page https://boutique.example/p/poussette-yoyo kept in recorded/:
  Poussette Yoyo³ & nacelle, Stokke, sold by Boutique SAS
  449.90 EUR, barcode 8716134000123, 2 pictures - read from its json-ld

omnitrade/paypal, 12.50 EUR for ORDER-1042, from the answers kept in recorded/:
  order 5O190127TN364715T, pending - the buyer goes to https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T

Loaded from Symfony: Symfony\Component\HttpClient, Symfony\Contracts\HttpClient, Symfony\Contracts\Service
Classes of a framework (DependencyInjection, Config, HttpKernel, HttpFoundation, a bundle, a bridge, Doctrine, Twig): none
```

The answers in `recorded/` were not taken from PayPal nor from a shop: PayPal's are written in
the shape its Orders v2 documentation gives, with that documentation's example ids and a token
that is manifestly not one; the page is omnitrade/web's own fixture, of a shop that does not
exist. `bare --recorded --json` prints the same whole - every class loaded, every file since the
autoloader - without a call: `Tests/BareTest.php` runs it in a process of its own and checks the
list.

`omnitrade/stripe` stands on Omnipay, which requires `symfony/http-foundation` for its own
request object: installed with it, loaded only when a Stripe payment is made - a library there,
with no kernel and no container.

The image is `php:8.4-cli-alpine` with Composer and `bcmath`; the harness's packages live in the
`harness` volume of the `omnitrade-harness` project.
