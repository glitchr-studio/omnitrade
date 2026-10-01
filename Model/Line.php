<?php

namespace Omnitrade\Model;

/** One line of what is paid for, as the provider's page or invoice lists it. */
final readonly class Line
{
    public function __construct(
        public string $label,
        public Money $unitAmount,
        public int $quantity = 1,
        public ?string $sku = null,
        public ?string $imageUrl = null,
        /** The provider's own id for it, when it has one (a Shopify variant gid, a WooCommerce product id). */
        public ?string $reference = null,
        /** Goods to post, as opposed to a service or a download. */
        public bool $physical = false,
    ) {
    }

    public function total(): Money
    {
        return new Money($this->unitAmount->amount * $this->quantity, $this->unitAmount->currency);
    }
}
