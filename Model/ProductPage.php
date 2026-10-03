<?php

namespace Omnitrade\Model;

/** One page of a catalogue: its products, and the cursor of the next page when there is one. */
final readonly class ProductPage
{
    public function __construct(
        /** @var list<Product> */
        public array $products = [],
        /** Hand it to the next FetchProducts; null on the last page. */
        public ?string $next = null,
    ) {
    }

    public function hasMore(): bool
    {
        return null !== $this->next;
    }
}
