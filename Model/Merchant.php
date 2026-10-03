<?php

namespace Omnitrade\Model;

/**
 * Who sells: the shop itself on a platform of one's own, one seller among
 * many on a marketplace. An Offer names it when it is not the shop.
 */
final readonly class Merchant
{
    public function __construct(
        public string $name,
        public ?string $reference = null,
        public ?string $url = null,
        /** ISO 3166-1 alpha-2. */
        public ?string $country = null,
    ) {
    }
}
