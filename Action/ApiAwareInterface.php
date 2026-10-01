<?php

namespace Omnitrade\Action;

/** An action that talks to the provider through the gateway's API client. */
interface ApiAwareInterface
{
    public function setApi(object $api): void;
}
