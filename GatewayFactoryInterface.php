<?php

namespace Omnitrade;

/** Builds a provider's gateway from its options (credentials, sandbox...). */
interface GatewayFactoryInterface
{
    /** The name gateways are configured with: "stripe", "paypal", "shopify"... */
    public function getName(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options = []): GatewayInterface;
}
