---
title: Subscriptions
order: 40
---

# Subscriptions

A payment that renews: the buyer subscribes on the provider's page, the
provider charges every period and tells of each step by webhook.

```php
$transaction = $gateway->subscribe(new Payment(
    Money::of(1900, 'EUR'), 'PLAN-D-42', 'Formule D',
    new Customer(email: 'lea@example.org'),
    returnUrl: $return, metadata: ['order' => 'PLAN-D-42'],
), 'month');
return new RedirectResponse($transaction->redirectUrl);

$subscription = $gateway->fetchSubscription('sub_...');
$subscription->isActive();            // active or trialing
$subscription->currentPeriodEnd;      // what was paid for lasts until then

$gateway->cancelSubscription('sub_...');          // at the end of the paid period
$gateway->cancelSubscription('sub_...', false);   // at once
$gateway->subscriptionPortal($subscription->customer, $returnUrl, 'fr');   // card, invoices, cancellation: the provider's page
```

| Request | Result | Shortcut |
|---|---|---|
| `Subscribe(Payment, interval = month, intervalCount = 1, ?price, ?trialDays, ?customer)` | `Transaction` (PENDING, `redirectUrl`) | `subscribe()` |
| `FetchSubscription(reference)` | `Subscription` | `fetchSubscription()` |
| `CancelSubscription(reference, atPeriodEnd = true)` | `Subscription` | `cancelSubscription()` |
| `SubscriptionPortal(customer, returnUrl, ?locale)` | the page's URL | `subscriptionPortal()` |

The payment's amount is what each period costs, its description what is
subscribed to; `price` names a price kept at the provider instead. Its
metadata follows the subscription: every later event carries it.

`Model\Subscription`: provider, reference, status (`active`, `trialing`,
`past_due`, `unpaid`, `cancelled`, `incomplete`, `paused`), customer, amount,
interval, intervalCount, currentPeriodStart, currentPeriodEnd,
cancelAtPeriodEnd, cancelledAt, price, metadata, raw.

## Webhooks

- the first payment is the session's: `Notification::$transaction` PAID, its
  `raw['subscription']` the subscription's id, `raw['customer']` the customer;
- the subscription's life (`customer.subscription.created`, `.updated`,
  `.deleted` with Stripe): `Notification::$subscription`, `isSubscription()`
  true, `$reference` its id. A renewal moves `currentPeriodEnd`; a payment
  that fails makes it `past_due`; its end, `cancelled`.

Only `omnitrade/stripe` answers these requests (Checkout in subscription
mode, the Billing customer portal).
