<?php

namespace Omnitrade\Model;

/** Where a transaction stands, whatever the provider calls it. */
enum Status: string
{
    /** Started, not settled: the buyer is on the provider's page, a transfer is on its way. */
    case PENDING = 'pending';
    /** The money is reserved; capture() takes it. */
    case AUTHORIZED = 'authorized';
    case PAID = 'paid';
    case REFUSED = 'refused';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case REFUNDED = 'refunded';

    /** Nothing more will happen to it. */
    public function isFinal(): bool
    {
        return !\in_array($this, [self::PENDING, self::AUTHORIZED], true);
    }

    /** The money came in (and may since have gone back). */
    public function isSettled(): bool
    {
        return \in_array($this, [self::PAID, self::PARTIALLY_REFUNDED, self::REFUNDED], true);
    }
}
