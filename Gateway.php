<?php

namespace Omnitrade;

use Omnitrade\Action\ActionInterface;
use Omnitrade\Exception\ProviderException;
use Omnitrade\Exception\RequestNotSupportedException;
use Omnitrade\Model\Money;
use Omnitrade\Model\Notification;
use Omnitrade\Model\Payment;
use Omnitrade\Model\PlatformOrder;
use Omnitrade\Model\Refund as RefundModel;
use Omnitrade\Model\Transaction;
use Omnitrade\Request;

/** A provider's actions behind one door: the first action supporting a request answers it. */
final class Gateway implements GatewayInterface
{
    /** @param ActionInterface[] $actions */
    public function __construct(
        private readonly string $name,
        private readonly string $title,
        private readonly array $actions,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function supports(string $request): bool
    {
        $probe = (new \ReflectionClass($request))->newInstanceWithoutConstructor();
        foreach ($this->actions as $action) {
            if ($action->supports($probe)) {
                return true;
            }
        }

        return false;
    }

    public function execute(Request\Request $request): Request\Request
    {
        foreach ($this->actions as $action) {
            if ($action->supports($request)) {
                $action->execute($request);
                if (!$request->isAnswered()) {
                    throw new ProviderException($this->name, \sprintf('%s left the request unanswered.', $action::class));
                }

                return $request;
            }
        }

        throw RequestNotSupportedException::for($request, $this->name);
    }

    public function purchase(Payment $payment): Transaction
    {
        return $this->execute(new Request\Purchase($payment))->getTransaction();
    }

    public function authorize(Payment $payment): Transaction
    {
        return $this->execute(new Request\Authorize($payment))->getTransaction();
    }

    public function capture(string $reference, ?Money $amount = null, ?string $idempotencyKey = null): Transaction
    {
        return $this->execute(new Request\Capture($reference, $amount, $idempotencyKey))->getTransaction();
    }

    public function refund(string $reference, ?Money $amount = null, ?string $idempotencyKey = null, ?string $reason = null): RefundModel
    {
        return $this->execute(new Request\Refund($reference, $amount, $idempotencyKey, $reason))->getRefund();
    }

    public function void(string $reference): Transaction
    {
        return $this->execute(new Request\VoidAuthorization($reference))->getTransaction();
    }

    public function fetch(string $reference): Transaction
    {
        return $this->execute(new Request\FetchTransaction($reference))->getTransaction();
    }

    public function paymentMethods(?Money $for = null, ?string $country = null): array
    {
        return $this->execute(new Request\GetPaymentMethods($for, $country))->getMethods();
    }

    public function notify(string $body, array $headers = []): Notification
    {
        return $this->execute(new Request\Notify($body, $headers))->getNotification();
    }

    public function fetchOrder(string $reference): PlatformOrder
    {
        return $this->execute(new Request\FetchOrder($reference))->getOrder();
    }
}
