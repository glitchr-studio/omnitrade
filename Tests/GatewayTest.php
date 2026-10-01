<?php

namespace Omnitrade\Tests;

use Omnitrade\Exception\InvalidConfigException;
use Omnitrade\Exception\RequestNotSupportedException;
use Omnitrade\Model\Money;
use Omnitrade\Model\Status;
use Omnitrade\Registry;
use Omnitrade\Request\Capture;
use Omnitrade\Request\Purchase;
use PHPUnit\Framework\TestCase;

final class GatewayTest extends TestCase
{
    public function testTheActionSupportingARequestAnswersIt(): void
    {
        $gateway = (new StubFactory())->create(['token' => 't']);

        $transaction = $gateway->purchase(Fixtures::payment());

        self::assertSame('stub', $transaction->provider);
        self::assertSame('tx_t_1', $transaction->reference);
        self::assertTrue($transaction->isPaid());
        self::assertSame('attempt-1', $transaction->raw['idempotency'], 'what the provider answered is kept whole');
        self::assertTrue($gateway->supports(Purchase::class));
        self::assertFalse($gateway->supports(Capture::class));
        self::assertSame('tx_t_1', $gateway->fetch('tx_t_1')->reference);
    }

    public function testAnUnsupportedRequestSaysWhichGatewayAndWhat(): void
    {
        $gateway = (new StubFactory())->create(['token' => 't']);

        $this->expectException(RequestNotSupportedException::class);
        $this->expectExceptionMessage('The "stub" gateway does not support Capture.');
        $gateway->capture('tx_t_1');
    }

    public function testRequiredOptionsAreChecked(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('The "stub" gateway needs: token.');
        (new StubFactory())->create();
    }

    public function testTheRegistryBuildsEachGatewayOnceByName(): void
    {
        $registry = new Registry([new StubFactory()], ['card' => ['factory' => 'stub', 'options' => ['token' => 'a']]]);

        self::assertTrue($registry->has('card'));
        self::assertFalse($registry->has('paypal'));
        self::assertSame($registry->get('card'), $registry->get('card'));
        self::assertSame('tx_a_1', $registry->get('card')->purchase(Fixtures::payment())->reference);
        self::assertSame(['stub'], $registry->factories());

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('No "paypal" gateway; configured: card.');
        $registry->get('paypal');
    }

    public function testMoneyKnowsItsMinorUnits(): void
    {
        self::assertSame('12.50', Money::of(1250, 'eur')->decimal());
        self::assertSame('EUR', Money::of(1250, 'eur')->currency);
        self::assertSame('1250', Money::of(1250, 'JPY')->decimal());
        self::assertSame('1.250', Money::of(1250, 'KWD')->decimal());
        self::assertSame(1250, Money::fromDecimal('12.50', 'EUR')->amount);
        self::assertSame(1250, Money::fromDecimal(1250, 'JPY')->amount);
        self::assertTrue(Fixtures::payment()->linesAddUp());
        $withShipping = new \Omnitrade\Model\Payment(Money::of(1250 + 490 - 100, 'EUR'), 'ORDER-1', lines: Fixtures::payment()->lines, shipping: Money::of(490, 'EUR'), discount: Money::of(100, 'EUR'));
        self::assertTrue($withShipping->linesAddUp(), 'shipping and discount count');
        self::assertFalse(new \Omnitrade\Model\Payment(Money::of(1300, 'EUR'), 'ORDER-1', lines: Fixtures::payment()->lines)->linesAddUp(), 'lines that do not add up: the amount goes as one line');
    }

    public function testAStatusKnowsWhetherItIsOver(): void
    {
        self::assertFalse(Status::PENDING->isFinal());
        self::assertFalse(Status::AUTHORIZED->isFinal());
        self::assertTrue(Status::PAID->isFinal());
        self::assertTrue(Status::PAID->isSettled());
        self::assertTrue(Status::REFUNDED->isSettled());
        self::assertFalse(Status::REFUSED->isSettled());
    }
}
