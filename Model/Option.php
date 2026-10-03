<?php

namespace Omnitrade\Model;

/**
 * What a product's variants differ by - "Millésime: 2018, 2019", "Format:
 * 75 cl, Magnum" - as the platform declares it. A variant says its own value
 * of each in ProductVariant::$options.
 */
final readonly class Option
{
    public function __construct(
        public string $name,
        /** @var list<string> */
        public array $values = [],
    ) {
    }
}
