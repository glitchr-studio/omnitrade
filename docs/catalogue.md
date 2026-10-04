---
title: Catalogue
order: 20
---

# The catalogue: products, variants, offers, stock

The same gateways that take payments read a platform's catalogue: a shop kept
in Shopify, WooCommerce or Stripe's own Products is read through one contract,
and a site copies it into its own tables (omnibase/marketplace's
`marketplace:catalogue:sync`) or shows it as it is.

```php
$gateway = $registry->get('shopify');

$page = $gateway->fetchProducts();                                   // the first page
while ($page->hasMore()) {
    $page = $gateway->fetchProducts($page->next);                    // the next one, by its cursor
}
$gateway->fetchProducts(updatedSince: new \DateTimeImmutable('-1 day'));   // what changed
$gateway->fetchProducts(query: 'margaux');                           // the platform's own search

$product = $gateway->fetchProduct('gid://shopify/Product/42');       // by its id there
$product = $gateway->fetchProduct('https://cave.example/products/margaux-2019');   // or by its page

$stocks = $gateway->fetchInventory(['gid://shopify/ProductVariant/7']);
```

## Models

All `final readonly`, under `Omnitrade\Model`:

| Model | What it holds |
|---|---|
| `Product` | provider, reference (the platform's id), title, description (HTML), handle, brand (Shopify's vendor, WooCommerce's brand), url, status (`active`, `draft`, `archived`), tags, categories, attributes (key/value), options, variants, media, merchant, updatedAt, raw |
| `ProductVariant` | reference, title (null for an only variant), sku, barcode, offers, options (its value of each option), stock, image, attributes, weight (grams), raw |
| `Offer` | price (`Money`), compareAt, available, url, merchant (null: the shop itself), reference (a Stripe price), taxIncluded |
| `Stock` | reference (the variant), quantity (null: not counted), tracked, sku, location, item (a Shopify inventory item) |
| `Media` | url, alt, type (`image`, `video`), width, height, reference |
| `Option` | name, values: what the variants differ by ("Millésime: 2018, 2019") |
| `Merchant` | name, reference, url, country: who sells, when it is not the shop |
| `Reference` | id or url: `Reference::of()` tells them apart; `slug()` is the page's last segment |
| `ProductPage` | products, next (the cursor of the next page, null on the last) |

`ProductVariant::price()` is its first offer's price (the shop's own);
`Product::price()` the lowest of its variants'. What the platform answered is
kept in `$raw`.

## Requests

| Request | Result | Shortcut |
|---|---|---|
| `FetchProducts(?cursor, ?updatedSince, ?query, limit = 50)` | `ProductPage` | `fetchProducts()` |
| `FetchProduct(Reference\|string)` | `?Product` | `fetchProduct()` |
| `FetchInventory(list<string> $references = [])` | `list<Stock>` | `fetchInventory()` |
| `AffiliateLink(Reference\|string, ?tag)` | `?string` (a URL; null: no programme for it) | `affiliateLink()` |

A provider says what it reads with `supports()`: Stripe has no stock, so
`supports(FetchInventory::class)` is false there and the stock stays the
site's.

| Provider | FetchProducts | FetchProduct (id / URL) | FetchInventory |
|---|---|---|---|
| `omnitrade/shopify` | Admin GraphQL `products` (`endCursor`), `updated_at:>` and search filters | gid, numeric id, handle / handle from `/products/<handle>`, id from `/admin/products/<id>` | `nodes(ids:)` on the variants, or every `productVariants` |
| `omnitrade/woocommerce` | REST v3 `/products` (page number, `modified_after`, `search`), variations | id (a variation's gives its product), slug / slug, `?p=<id>` | `stock_quantity` of products and variations |
| `omnitrade/web` | not supported | none / any product page: JSON-LD `Product` or `ProductGroup`, microdata, OpenGraph | not supported |
| `omnitrade/amazon` | Creators API `searchItems` (a query is required; no paging) | ASIN / an Amazon page naming one (`getItems`) | not supported |
| `omnitrade/stripe` | Products (`starting_after`; `search` on `name~` for a query; `updatedSince` filtered among each page) with their active Prices | `prod_…` / none | not supported |

A cursor is a position, not a search: hand the same `updatedSince` and `query`
with it to the next `FetchProducts`. A provider that filters a page after
reading it (Stripe's `updatedSince`) may give a short or empty page that
still has a next one: page on while `hasMore()`.

Each provider's `docs/catalogue.md` says what it maps how.

## Webhooks

A product or stock event goes through `notify()` like a payment's: the
`Notification` then carries `$product` (null for a deletion; `$reference` is
the product's id) or `$stocks`, and `isCatalogue()` is true.

```php
$notification = $gateway->notify($request->getContent(), $request->headers->all());
if ($notification->isCatalogue()) {
    // update the copy: $notification->product, $notification->stocks
}
```

## Affiliate links

`affiliateLink()` gives the address that sends a buyer to a product with the
site's tag, or null when the provider has no partner programme for it:

```php
$registry->get('amazon')->affiliateLink('https://www.amazon.fr/dp/B0C1234567/ref=x');   // https://www.amazon.fr/dp/B0C1234567?tag=mysite-21
$registry->get('web')->affiliateLink('https://www.fnac.com/a1');                        // the parameters configured for fnac.com, or null
```

`omnitrade/amazon` needs only the partner tag for it (no API call);
`omnitrade/web` adds the query parameters its `affiliate` option maps to the
page's host, and drops the tracking parameters the address came with.
