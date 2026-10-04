<?php

namespace Omnitrade\Harness;

use Omnitrade\Exception\InvalidConfigException;
use Omnitrade\Exception\OmnitradeException;
use Omnitrade\GatewayFactoryInterface;
use Omnitrade\GatewayInterface;
use Omnitrade\Model\Customer;
use Omnitrade\Model\Line;
use Omnitrade\Model\Money;
use Omnitrade\Model\Payment;
use Omnitrade\Model\Product;
use Omnitrade\Model\Stock;
use Omnitrade\Registry;
use Omnitrade\Request;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * The console that exercises every provider with the keys in .env:
 * gateways, methods, purchase, authorize, capture, void, fetch, refund,
 * notify, order, catalogue. Results are printed whole, as JSON; the
 * catalogue as tables.
 */
final class Console
{
    /** @var array<string, array{factory: string, needs: list<string>, options: array<string, mixed>}> */
    private array $config;

    /** @var array<string, GatewayFactoryInterface> */
    private array $factories = [];

    private Registry $registry;

    private function __construct()
    {
        $this->config = require __DIR__.'/../config/gateways.php';
        $http = HttpClient::create();
        foreach (require __DIR__.'/../plugins.php' as [, $class]) {
            if (class_exists($class)) {
                $factory = new $class($http);
                $this->factories[$factory->getName()] = $factory;
            }
        }
        $configured = array_filter($this->config, static fn (array $g) => !array_filter($g['needs'], static fn (string $key) => false === getenv($key) || '' === getenv($key)));
        $this->registry = new Registry($this->factories, array_map(static fn (array $g) => ['factory' => $g['factory'], 'options' => array_filter($g['options'], static fn ($v) => null !== $v)], $configured));
    }

    public static function create(): Application
    {
        $self = new self();
        $gateway = new InputArgument('gateway', InputArgument::REQUIRED);
        $reference = new InputArgument('reference', InputArgument::REQUIRED, 'The provider\'s reference of the transaction');
        $amount = static fn (string $description) => new InputOption('amount', 'a', InputOption::VALUE_REQUIRED, $description.' (minor units)');
        $currency = new InputOption('currency', 'c', InputOption::VALUE_REQUIRED, '', 'EUR');
        $app = new Application('omnitrade', '1.x');
        $app->addCommand($self->command('gateways', 'Which providers are installed and which are configured from .env', [], fn ($in, $out) => $self->gateways($out)));
        $app->addCommand($self->command('methods', 'The payment methods the provider offers', [$gateway, $amount('For this amount'), $currency, new InputOption('country', null, InputOption::VALUE_REQUIRED)], fn ($in, $out) => $self->print($out, $self->gateway($in)->paymentMethods($in->getOption('amount') ? Money::of((int) $in->getOption('amount'), $in->getOption('currency')) : null, $in->getOption('country')))));
        $app->addCommand($self->command('purchase', 'A payment: its page to open, or its state', [$gateway, $amount('The amount'), $currency, new InputOption('reference', 'r', InputOption::VALUE_REQUIRED, '', 'OMNITRADE-'.date('ymd-His')), new InputOption('method', 'm', InputOption::VALUE_REQUIRED, 'A payment method to insist on'), new InputOption('line', 'l', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A line: "label=unit amount[xquantity][@reference]"')], fn ($in, $out) => $self->print($out, $self->gateway($in)->purchase($self->payment($in)))));
        $app->addCommand($self->command('authorize', 'An authorization to capture later', [$gateway, $amount('The amount'), $currency, new InputOption('reference', 'r', InputOption::VALUE_REQUIRED, '', 'OMNITRADE-'.date('ymd-His')), new InputOption('method', 'm', InputOption::VALUE_REQUIRED), new InputOption('line', 'l', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY)], fn ($in, $out) => $self->print($out, $self->gateway($in)->authorize($self->payment($in)))));
        $app->addCommand($self->command('capture', 'An authorization captured', [$gateway, $reference, $amount('Part of it'), $currency], fn ($in, $out) => $self->print($out, $self->gateway($in)->capture($in->getArgument('reference'), $in->getOption('amount') ? Money::of((int) $in->getOption('amount'), $in->getOption('currency')) : null))));
        $app->addCommand($self->command('void', 'An authorization voided', [$gateway, $reference], fn ($in, $out) => $self->print($out, $self->gateway($in)->void($in->getArgument('reference')))));
        $app->addCommand($self->command('fetch', 'Where a transaction stands', [$gateway, $reference], fn ($in, $out) => $self->print($out, $self->gateway($in)->fetch($in->getArgument('reference')))));
        $app->addCommand($self->command('refund', 'A refund, whole or partial', [$gateway, $reference, $amount('Part of it'), $currency, new InputOption('reason', null, InputOption::VALUE_REQUIRED)], fn ($in, $out) => $self->print($out, $self->gateway($in)->refund($in->getArgument('reference'), $in->getOption('amount') ? Money::of((int) $in->getOption('amount'), $in->getOption('currency')) : null, 'refund-'.date('ymd-His'), $in->getOption('reason')))));
        $app->addCommand($self->command('notify', 'A webhook read from stdin, checked and decoded', [$gateway, new InputOption('header', 'H', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '"Name: value", the signature header among them')], function ($in, $out) use ($self) {
            $headers = [];
            foreach ($in->getOption('header') as $header) {
                [$name, $value] = array_map('trim', array_pad(explode(':', $header, 2), 2, ''));
                $headers[$name] = $value;
            }
            $self->print($out, $self->gateway($in)->notify((string) stream_get_contents(\STDIN), $headers));
        }));
        $app->addCommand($self->command('order', 'A platform\'s order', [$gateway, $reference], fn ($in, $out) => $self->print($out, $self->gateway($in)->fetchOrder($in->getArgument('reference')))));
        $app->addCommand($self->command('catalogue', 'The catalogue: products, their variants, prices and stock', [$gateway,
            new InputOption('query', null, InputOption::VALUE_REQUIRED, 'The platform\'s own search'),
            new InputOption('since', null, InputOption::VALUE_REQUIRED, 'Only what changed since: a date, or "-1 day"'),
            new InputOption('limit', null, InputOption::VALUE_REQUIRED, 'Products per page', '10'),
            new InputOption('cursor', null, InputOption::VALUE_REQUIRED, 'The page this cursor (printed under the previous one) points to'),
            new InputOption('product', null, InputOption::VALUE_REQUIRED, 'One product, by its id there or the address of its page'),
            new InputOption('inventory', null, InputOption::VALUE_NONE, 'The stock levels: of --product\'s variants, or of every variant'),
        ], fn ($in, $out) => $self->catalogue($in, $out)));

        $app->addCommand($self->command('link', 'The affiliate link of a product, by its id or the address of its page', [$gateway, $reference, new InputOption('tag', null, InputOption::VALUE_REQUIRED, 'A tag other than the configured one')], fn ($in, $out) => $out->writeln($self->gateway($in)->affiliateLink($in->getArgument('reference'), $in->getOption('tag')) ?? '<comment>No affiliate programme for this address.</comment>')));
        $app->addCommand($self->command('account', 'A connected account: opened (--create FR), read, or its onboarding page (--link)', [$gateway, new InputArgument('reference', InputArgument::OPTIONAL, 'The account, by its id there'),
            new InputOption('create', null, InputOption::VALUE_REQUIRED, 'Open an Express account in this country'),
            new InputOption('email', null, InputOption::VALUE_REQUIRED),
            new InputOption('link', null, InputOption::VALUE_NONE, 'The page where its holder completes it'),
        ], function ($in, $out) use ($self) {
            $g = $self->gateway($in);
            if ($in->getOption('create')) {
                $self->print($out, $g->createAccount($in->getOption('create'), $in->getOption('email')));
            } elseif ($in->getOption('link')) {
                $out->writeln($g->accountLink((string) $in->getArgument('reference'), (string) getenv('HARNESS_RETURN_URL'), (string) getenv('HARNESS_CANCEL_URL')));
            } else {
                $self->print($out, $g->fetchAccount((string) $in->getArgument('reference')));
            }
        }));
        $app->addCommand($self->command('subscribe', 'A subscription: its page to open (the amount every --interval)', [$gateway, $amount('The amount per period'), $currency, new InputOption('reference', 'r', InputOption::VALUE_REQUIRED, '', 'OMNITRADE-'.date('ymd-His')), new InputOption('method', 'm', InputOption::VALUE_REQUIRED), new InputOption('line', 'l', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY), new InputOption('interval', null, InputOption::VALUE_REQUIRED, 'day, week, month or year', 'month'), new InputOption('price', null, InputOption::VALUE_REQUIRED, 'A price kept at the provider')], fn ($in, $out) => $self->print($out, $self->gateway($in)->subscribe($self->payment($in), $in->getOption('interval'), 1, $in->getOption('price')))));
        $app->addCommand($self->command('subscription', 'A subscription: read, or stopped (--cancel, --now)', [$gateway, $reference, new InputOption('cancel', null, InputOption::VALUE_NONE), new InputOption('now', null, InputOption::VALUE_NONE, 'At once rather than at the end of the paid period')], fn ($in, $out) => $self->print($out, $in->getOption('cancel') ? $self->gateway($in)->cancelSubscription($in->getArgument('reference'), !$in->getOption('now')) : $self->gateway($in)->fetchSubscription($in->getArgument('reference')))));

        return $app;
    }

    /** @param list<InputArgument|InputOption> $definition */
    private function command(string $name, string $description, array $definition, \Closure $code): Command
    {
        $command = new Command($name);
        $command->setDescription($description)->setDefinition($definition);
        $command->setCode(function (InputInterface $in, OutputInterface $out) use ($code): int {
            try {
                $code($in, $out);

                return Command::SUCCESS;
            } catch (InvalidConfigException $e) {
                $out->writeln('<error>'.$e->getMessage().'</error>');

                return Command::INVALID;
            } catch (OmnitradeException $e) {
                $out->writeln('<error>'.$e->getMessage().'</error>');

                return Command::FAILURE;
            }
        });

        return $command;
    }

    private function gateways(OutputInterface $out): void
    {
        $requests = ['purchase' => Request\Purchase::class, 'authorize' => Request\Authorize::class, 'capture' => Request\Capture::class, 'refund' => Request\Refund::class, 'fetch' => Request\FetchTransaction::class, 'methods' => Request\GetPaymentMethods::class, 'notify' => Request\Notify::class, 'order' => Request\FetchOrder::class, 'products' => Request\FetchProducts::class, 'product' => Request\FetchProduct::class, 'inventory' => Request\FetchInventory::class];
        $table = new Table($out);
        $table->setHeaders(['Gateway', 'Factory', 'Installed', 'Configured', 'Does']);
        foreach ($this->config as $name => $gateway) {
            $installed = isset($this->factories[$gateway['factory']]);
            $missing = array_filter($gateway['needs'], static fn (string $key) => false === getenv($key) || '' === getenv($key));
            $does = '';
            if ($installed && !$missing) {
                $g = $this->registry->get($name);
                $does = implode(' ', array_keys(array_filter($requests, static fn (string $class) => $g->supports($class))));
            }
            $table->addRow([$name, $gateway['factory'], $installed ? '<info>yes</info>' : '<comment>no</comment>', $missing ? '<comment>needs '.implode(', ', $missing).'</comment>' : ($installed ? '<info>yes</info>' : ''), $does]);
        }
        $table->render();
    }

    private function catalogue(InputInterface $in, OutputInterface $out): void
    {
        $gateway = $this->gateway($in);
        if (null !== $reference = $in->getOption('product')) {
            $product = $gateway->fetchProduct($reference);
            if (null === $product) {
                $out->writeln('<comment>No such product.</comment>');

                return;
            }
            $this->products($out, [$product]);
            $out->writeln(array_filter([
                '<info>Reference:</info> '.$product->reference.($product->handle ? ' ('.$product->handle.')' : ''),
                $product->url ? '<info>Page:</info> '.$product->url : null,
                $product->categories ? '<info>Categories:</info> '.implode(', ', $product->categories) : null,
                $product->tags ? '<info>Tags:</info> '.implode(', ', $product->tags) : null,
                $product->options ? '<info>Options:</info> '.implode('; ', array_map(static fn ($o) => $o->name.': '.implode(', ', $o->values), $product->options)) : null,
                $product->attributes ? '<info>Attributes:</info> '.implode('; ', array_map(static fn ($k, $v) => $k.': '.(\is_array($v) ? implode(', ', $v) : $v), array_keys($product->attributes), $product->attributes)) : null,
                $product->media ? '<info>Media:</info> '.implode(' ', array_map(static fn ($m) => $m->url, $product->media)) : null,
            ]));
            if ($in->getOption('inventory')) {
                $this->stocks($out, $gateway->fetchInventory(array_map(static fn ($v) => $v->reference, $product->variants)));
            }

            return;
        }
        if ($in->getOption('inventory')) {
            $this->stocks($out, $gateway->fetchInventory());

            return;
        }
        $since = $in->getOption('since');
        $page = $gateway->fetchProducts($in->getOption('cursor'), null !== $since ? new \DateTimeImmutable($since) : null, $in->getOption('query'), max(1, (int) $in->getOption('limit')));
        $this->products($out, $page->products);
        $out->writeln($page->hasMore() ? '<info>Next page:</info> --cursor='.escapeshellarg((string) $page->next) : 'The last page.');
    }

    /** @param list<Product> $products */
    private function products(OutputInterface $out, array $products): void
    {
        $table = new Table($out);
        $table->setHeaders(['Product', 'Brand', 'Status', 'Variant', 'SKU', 'Price', 'Compare at', 'Stock']);
        foreach ($products as $product) {
            foreach ($product->variants as $i => $variant) {
                $offer = $variant->offer();
                $table->addRow([
                    0 === $i ? $product->title."\n".$product->reference : '',
                    0 === $i ? (string) $product->brand : '',
                    0 === $i ? $product->status : '',
                    ($variant->title ?? '-')."\n".$variant->reference,
                    (string) $variant->sku,
                    $offer ? (string) $offer->price.($offer->available ? '' : ' (unavailable)') : '-',
                    $offer?->compareAt ? (string) $offer->compareAt : '',
                    self::quantity($variant->stock),
                ]);
            }
        }
        $table->render();
        $out->writeln(\count($products).' product(s).');
    }

    /** @param list<Stock> $stocks */
    private function stocks(OutputInterface $out, array $stocks): void
    {
        $table = new Table($out);
        $table->setHeaders(['Variant', 'SKU', 'Stock', 'Location', 'Item']);
        foreach ($stocks as $stock) {
            $table->addRow([$stock->reference, (string) $stock->sku, self::quantity($stock), (string) $stock->location, (string) $stock->item]);
        }
        $table->render();
        $out->writeln(\count($stocks).' stock level(s).');
    }

    private static function quantity(?Stock $stock): string
    {
        return match (true) {
            null === $stock => '-',
            !$stock->tracked || null === $stock->quantity => 'not counted',
            default => (string) $stock->quantity,
        };
    }

    private function gateway(InputInterface $in): GatewayInterface
    {
        return $this->registry->get($in->getArgument('gateway'));
    }

    private function payment(InputInterface $in): Payment
    {
        $currency = strtoupper((string) $in->getOption('currency'));
        $lines = [];
        foreach ((array) $in->getOption('line') as $spec) {
            if (preg_match('/^(?<label>[^=]+)=(?<unit>\d+)(?:x(?<qty>\d+))?(?:@(?<ref>.+))?$/', $spec, $m)) {
                $lines[] = new Line($m['label'], Money::of((int) $m['unit'], $currency), (int) ($m['qty'] ?: 1), reference: $m['ref'] ?: null);
            }
        }
        $amount = (int) ($in->getOption('amount') ?? array_sum(array_map(static fn (Line $l) => $l->unitAmount->amount * $l->quantity, $lines)) ?: 1990);
        $env = static fn (string $key, ?string $default = null): ?string => (false !== ($v = getenv($key)) && '' !== $v) ? $v : $default;

        return new Payment(Money::of($amount, $currency), $in->getOption('reference'), 'Omnitrade harness '.$in->getOption('reference'),
            new Customer($env('HARNESS_CUSTOMER_EMAIL', 'buyer@example.org'), $env('HARNESS_CUSTOMER_NAME', 'Émile Zola')),
            $lines ?: [new Line('Harness purchase', Money::of($amount, $currency), 1)],
            returnUrl: $env('HARNESS_RETURN_URL', 'https://example.org/paid'), cancelUrl: $env('HARNESS_CANCEL_URL', 'https://example.org/cancelled'),
            idempotencyKey: $in->getOption('reference'), method: $in->hasOption('method') ? $in->getOption('method') : null);
    }

    private function print(OutputInterface $out, mixed $result): void
    {
        if (\is_object($result) && property_exists($result, 'redirectUrl') && $result->redirectUrl) {
            $out->writeln('<info>Open:</info> '.$result->redirectUrl);
        }
        $out->writeln((string) json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR));
    }
}
