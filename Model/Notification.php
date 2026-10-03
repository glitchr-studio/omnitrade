<?php

namespace Omnitrade\Model;

/**
 * What a provider is telling us, checked: one event about one transaction.
 * The event is the provider's own name for it; $status is what it means for
 * the transaction when it means anything (null for a hint to ignore), and
 * $transaction the transaction as the event carries it, when it does.
 *
 * A catalogue event (a product created, changed or deleted, a stock level
 * moved) is one too: $reference is then the product's id there, $product the
 * product as the event carries it (null for a deletion), $stocks the levels.
 */
final readonly class Notification
{
    public function __construct(
        public string $provider,
        /** The provider's event type: "checkout.session.completed", "orders/paid"... */
        public string $event,
        /** The transaction (or platform order) it concerns, by its reference there. */
        public ?string $reference = null,
        public ?Status $status = null,
        public ?Transaction $transaction = null,
        /** The provider's id for the event, to handle it once. */
        public ?string $id = null,
        public array $raw = [],
        public ?Product $product = null,
        /** @var list<Stock> */
        public array $stocks = [],
    ) {
    }

    /** About the catalogue rather than a payment. */
    public function isCatalogue(): bool
    {
        return null !== $this->product || [] !== $this->stocks || str_starts_with($this->event, 'product') || str_starts_with($this->event, 'inventory');
    }
}
