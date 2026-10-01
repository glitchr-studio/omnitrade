<?php

namespace Omnitrade\Model;

/** Who pays: what a provider wants to know of them, all optional. */
final readonly class Customer
{
    public function __construct(
        public ?string $email = null,
        public ?string $name = null,
        /** The provider's own id for them, when they have one (Stripe's cus_...). */
        public ?string $reference = null,
        public ?string $phone = null,
        /** @var list<string> */
        public array $street = [],
        public ?string $postalCode = null,
        public ?string $city = null,
        /** ISO 3166-1 alpha-2. */
        public ?string $country = null,
    ) {
    }
}
