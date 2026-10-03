<?php

namespace Omnitrade\Model;

/** A picture (or a film) of a product, as the platform serves it. */
final readonly class Media
{
    public const IMAGE = 'image';
    public const VIDEO = 'video';

    public function __construct(
        public string $url,
        public ?string $alt = null,
        public string $type = self::IMAGE,
        public ?int $width = null,
        public ?int $height = null,
        /** The platform's id for it, when it has one. */
        public ?string $reference = null,
    ) {
    }
}
