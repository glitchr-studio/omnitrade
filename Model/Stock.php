<?php

namespace Omnitrade\Model;

/**
 * How many of a variant are left, as the platform counts them. A platform
 * that does not track it says $tracked = false and $quantity = null: as many
 * as one likes.
 */
final readonly class Stock
{
    public function __construct(
        /** The variant (or the product, when it has no variants) by its id there. */
        public string $reference,
        public ?int $quantity,
        public bool $tracked = true,
        public ?string $sku = null,
        /** Where it is held, when the platform counts by location. */
        public ?string $location = null,
        /** The platform's own id for the counted item (a Shopify inventory item). */
        public ?string $item = null,
    ) {
    }

    public function available(): bool
    {
        return !$this->tracked || null === $this->quantity || $this->quantity > 0;
    }
}
