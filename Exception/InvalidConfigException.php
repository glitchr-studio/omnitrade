<?php

namespace Omnitrade\Exception;

/** A gateway misconfigured: a credential missing, an unknown factory. */
final class InvalidConfigException extends \LogicException implements OmnitradeException
{
}
