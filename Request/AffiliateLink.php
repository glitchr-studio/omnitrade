<?php

namespace Omnitrade\Request;

use Omnitrade\Model\Reference;

/**
 * Reserved: the address that sends a buyer to a product with the site's
 * affiliate tag (a marketplace's partner programme). No provider answers it
 * yet; supports() says false everywhere. Result: the URL.
 */
final class AffiliateLink extends Request
{
    public readonly Reference $reference;

    public function __construct(Reference|string $reference, public readonly ?string $tag = null)
    {
        $this->reference = Reference::of($reference);
    }

    public function getUrl(): ?string
    {
        return $this->getResult();
    }
}
