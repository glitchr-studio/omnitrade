<?php

namespace Omnitrade\Model;

/** An amount in a currency's minor units (cents): 1250 EUR is 12,50 €. */
final readonly class Money
{
    public string $currency;

    public function __construct(public int $amount, string $currency)
    {
        $this->currency = strtoupper($currency);
    }

    public static function of(int $amount, string $currency): self
    {
        return new self($amount, $currency);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    /** In major units, as a provider's decimal API expects ("12.50"); yen and the like have none. */
    public function decimal(): string
    {
        $digits = self::minorDigits($this->currency);

        return number_format($this->amount / (10 ** $digits), $digits, '.', '');
    }

    /** From a provider's decimal ("12.50"), to minor units. */
    public static function fromDecimal(string|float $decimal, string $currency): self
    {
        return new self((int) round(((float) $decimal) * (10 ** self::minorDigits($currency))), $currency);
    }

    /** How many minor digits a currency has: 2 for most, 0 for JPY, KRW..., 3 for BHD, KWD... */
    public static function minorDigits(string $currency): int
    {
        return match (strtoupper($currency)) {
            'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'UYI', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' => 0,
            'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND' => 3,
            default => 2,
        };
    }

    public function __toString(): string
    {
        return $this->decimal().' '.$this->currency;
    }
}
