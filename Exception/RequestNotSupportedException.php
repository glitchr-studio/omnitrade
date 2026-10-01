<?php

namespace Omnitrade\Exception;

use Omnitrade\Request\Request;

/** The provider does not do that (no authorizations, no platform orders...). */
final class RequestNotSupportedException extends \LogicException implements OmnitradeException
{
    public static function for(Request $request, string $gateway): self
    {
        return new self(\sprintf('The "%s" gateway does not support %s.', $gateway, (new \ReflectionClass($request))->getShortName()));
    }
}
