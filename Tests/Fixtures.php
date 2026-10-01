<?php

namespace Omnitrade\Tests;

use Omnitrade\Model\Customer;
use Omnitrade\Model\Line;
use Omnitrade\Model\Money;
use Omnitrade\Model\Payment;

final class Fixtures
{
    public static function payment(int $amount = 1250, string $currency = 'EUR'): Payment
    {
        return new Payment(
            amount: Money::of($amount, $currency),
            reference: 'ORDER-1042',
            description: 'Commande n° 1042',
            customer: new Customer(email: 'camille@example.org', name: 'Camille Durand', country: 'FR'),
            lines: [new Line('Dix heures', Money::of($amount, $currency))],
            returnUrl: 'https://shop.example/retour',
            cancelUrl: 'https://shop.example/panier',
            idempotencyKey: 'attempt-1',
            notice: 'Autoliquidation',
        );
    }
}
