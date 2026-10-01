<?php

namespace Omnitrade\Action;

use Omnitrade\Request\Request;

/** One thing a provider does: answers the requests it supports. */
interface ActionInterface
{
    public function supports(Request $request): bool;

    /** Sets the request's result, or throws Omnitrade\Exception\ProviderException. */
    public function execute(Request $request): void;
}
