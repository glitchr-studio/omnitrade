---
title: Connected accounts
order: 30
---

# Connected accounts: money for someone else

A platform that takes payments **for others** - the host of a gift fund, a
seller of a marketplace - never keeps that money: the provider opens an
account for each of them (Stripe Connect), the buyer pays the platform's
page, and the provider passes the payment on, less the platform's fee.

```php
$stripe = $registry->get('stripe');

// 1. An Express account for the host, once.
$account = $stripe->createAccount('FR', 'lea@example.org', metadata: ['holder' => (string) $user->getId()]);
// keep $account->reference ("acct_...")

// 2. The provider's page where they give their identity and bank account.
return new RedirectResponse($stripe->accountLink($account->reference, $returnUrl, $refreshUrl));

// 3. Back on $returnUrl (or on the account.updated webhook): is it ready?
$account = $stripe->fetchAccount($reference);
$account->isReady();          // chargesEnabled && payoutsEnabled
$account->requirements;       // what is still missing: ["external_account"]

// 4. A payment sent to it: a destination charge, the platform's fee kept.
$stripe->purchase(new Payment(
    Money::of(5000, 'EUR'), 'GIFT-7', 'Participation',
    returnUrl: $return,
    destination: $account->reference,
    applicationFee: Money::of(150, 'EUR'),
));
```

## Models and requests

| Request | Result | Shortcut |
|---|---|---|
| `CreateAccount(country, ?email, type = express, ?businessType, metadata, capabilities = [transfers], ?idempotencyKey)` | `Account` | `createAccount()` |
| `AccountLink(reference, returnUrl, refreshUrl, type = onboarding)` | the page's URL (single use, a few minutes) | `accountLink()` |
| `FetchAccount(reference)` | `Account` | `fetchAccount()` |

`Model\Account`: provider, reference, type, country, email,
`detailsSubmitted`, `chargesEnabled`, `payoutsEnabled`, `requirements`,
defaultCurrency, metadata, raw; `isReady()`.

`Model\Payment` takes two more arguments: `destination` (the connected
account) and `applicationFee` (a `Money` in the payment's currency, within
its amount; refused without a destination). A provider without connected
accounts ignores neither: it does not support `CreateAccount`, so nothing
can name a destination there.

## Webhooks

`notify()` hands an account's event back with `Notification::$account` (the
account as it stands) and `isAccount()` true; `$reference` is the account's
id. With Stripe, forward the *Connect* events to the same endpoint
(`stripe listen --forward-connect-to …`, or a Connect webhook endpoint in
the dashboard).

| Provider | CreateAccount | AccountLink | FetchAccount | destination / applicationFee |
|---|---|---|---|---|
| `omnitrade/stripe` | `POST /v1/accounts` (Express) | `POST /v1/account_links` | `GET /v1/accounts/{id}` | `payment_intent_data[transfer_data][destination]`, `[application_fee_amount]` on the Checkout session |
| others | not supported | | | |
