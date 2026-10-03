<?php

namespace Omnitrade;

use Omnitrade\Model\Money;
use Omnitrade\Model\Notification;
use Omnitrade\Model\Payment;
use Omnitrade\Model\PaymentMethod;
use Omnitrade\Model\PlatformOrder;
use Omnitrade\Model\Product;
use Omnitrade\Model\ProductPage;
use Omnitrade\Model\Reference;
use Omnitrade\Model\Refund;
use Omnitrade\Model\Stock;
use Omnitrade\Model\Transaction;
use Omnitrade\Request\Request;

/**
 * One provider, configured - a payment service (Stripe, PayPal) or a commerce
 * platform (Shopify, WooCommerce): the same questions for all of them, about
 * payments and about the catalogue. Each
 * typed method is a shortcut for execute() with its request; a provider that
 * does not do something throws RequestNotSupportedException, and supports()
 * says so beforehand.
 */
interface GatewayInterface
{
    public function getName(): string;

    public function getTitle(): string;

    /** @param class-string<Request> $request */
    public function supports(string $request): bool;

    /**
     * @template T of Request
     *
     * @param T $request
     *
     * @return T answered
     *
     * @throws Exception\RequestNotSupportedException
     * @throws Exception\ProviderException
     */
    public function execute(Request $request): Request;

    /** Take the money, now or through a page the buyer is sent to (a redirect, then notify()). */
    public function purchase(Payment $payment): Transaction;

    /** Reserve the money; capture() takes it, void() lets it go. */
    public function authorize(Payment $payment): Transaction;

    /** @param Money|null $amount part of the authorization, or all of it */
    public function capture(string $reference, ?Money $amount = null, ?string $idempotencyKey = null): Transaction;

    /** @param Money|null $amount part of the payment, or all of it */
    public function refund(string $reference, ?Money $amount = null, ?string $idempotencyKey = null, ?string $reason = null): Refund;

    public function void(string $reference): Transaction;

    /** The transaction as the provider has it now. */
    public function fetch(string $reference): Transaction;

    /** @return PaymentMethod[] what the provider can take, for this amount and country when given */
    public function paymentMethods(?Money $for = null, ?string $country = null): array;

    /**
     * What the provider is telling us (a webhook): checked against its
     * signature, read as one event about one transaction.
     *
     * @param array<string, string|string[]> $headers
     *
     * @throws Exception\InvalidNotificationException when the signature does not hold
     */
    public function notify(string $body, array $headers = []): Notification;

    /** A platform's own order (Shopify, WooCommerce): the sale as it stands there. */
    public function fetchOrder(string $reference): PlatformOrder;

    /** One product, by the platform's id or the address of its page; null when there is none. */
    public function fetchProduct(Reference|string $reference): ?Product;

    /** A page of the catalogue, from a cursor, changed since a date, matching a search. */
    public function fetchProducts(?string $cursor = null, ?\DateTimeInterface $updatedSince = null, ?string $query = null, int $limit = 50): ProductPage;

    /**
     * @param list<string> $references the variants, by their ids there (none: all)
     *
     * @return list<Stock>
     */
    public function fetchInventory(array $references = []): array;
}
