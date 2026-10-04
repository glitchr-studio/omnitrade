<?php

namespace Omnitrade;

use Omnitrade\Action\ActionInterface;
use Omnitrade\Exception\ProviderException;
use Omnitrade\Exception\RequestNotSupportedException;
use Omnitrade\Model\Account;
use Omnitrade\Model\Money;
use Omnitrade\Model\Notification;
use Omnitrade\Model\Payment;
use Omnitrade\Model\PlatformOrder;
use Omnitrade\Model\Product;
use Omnitrade\Model\ProductPage;
use Omnitrade\Model\Reference;
use Omnitrade\Model\Refund as RefundModel;
use Omnitrade\Model\Subscription;
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

    public function fetchProduct(Reference|string $reference): ?Product
    {
        return $this->execute(new Request\FetchProduct($reference))->getProduct();
    }

    public function fetchProducts(?string $cursor = null, ?\DateTimeInterface $updatedSince = null, ?string $query = null, int $limit = 50): ProductPage
    {
        return $this->execute(new Request\FetchProducts($cursor, $updatedSince, $query, $limit))->getPage();
    }

    public function fetchInventory(array $references = []): array
    {
        return $this->execute(new Request\FetchInventory($references))->getStocks();
    }

    public function affiliateLink(Reference|string $reference, ?string $tag = null): ?string
    {
        return $this->execute(new Request\AffiliateLink($reference, $tag))->getUrl();
    }

    public function createAccount(string $country, ?string $email = null, string $type = Account::EXPRESS, array $metadata = []): Account
    {
        return $this->execute(new Request\CreateAccount($country, $email, $type, null, $metadata))->getAccount();
    }

    public function accountLink(string $reference, string $returnUrl, string $refreshUrl): string
    {
        return $this->execute(new Request\AccountLink($reference, $returnUrl, $refreshUrl))->getUrl();
    }

    public function fetchAccount(string $reference): Account
    {
        return $this->execute(new Request\FetchAccount($reference))->getAccount();
    }

    public function subscribe(Payment $payment, string $interval = 'month', int $intervalCount = 1, ?string $price = null): Transaction
    {
        return $this->execute(new Request\Subscribe($payment, $interval, $intervalCount, $price))->getTransaction();
    }

    public function fetchSubscription(string $reference): Subscription
    {
        return $this->execute(new Request\FetchSubscription($reference))->getSubscription();
    }

    public function cancelSubscription(string $reference, bool $atPeriodEnd = true): Subscription
    {
        return $this->execute(new Request\CancelSubscription($reference, $atPeriodEnd))->getSubscription();
    }

    public function subscriptionPortal(string $customer, string $returnUrl, ?string $locale = null): string
    {
        return $this->execute(new Request\SubscriptionPortal($customer, $returnUrl, $locale))->getUrl();
    }
}
