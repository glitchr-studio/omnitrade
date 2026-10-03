<?php

namespace Omnitrade\Model;

/**
 * A price at which a variant can be bought, and from whom: on a platform of
 * one's own there is one (the shop's), on a marketplace there may be several.
 */
final readonly class Offer
{
    public function __construct(
        public Money $price,
        /** The struck-through price ("compare at"), when the platform shows one. */
        public ?Money $compareAt = null,
        public bool $available = true,
        /** Where to buy it (the product page, a seller's listing). */
        public ?string $url = null,
        /** null: the shop itself. */
        public ?Merchant $merchant = null,
        /** The platform's id for this price, when it has one (a Stripe price). */
        public ?string $reference = null,
        /** Taxes included in $price, as the platform says. */
        public ?bool $taxIncluded = null,
    ) {
    }
}
