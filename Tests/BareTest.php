<?php

namespace Omnitrade\Tests;

use Omnitrade\Bridge\Symfony\OmnitradeBundle;
use Omnitrade\PayPal\PayPalGatewayFactory;
use Omnitrade\Registry;
use Omnitrade\Web\WebGatewayFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Omnitrade outside Symfony: the harness's bare script (docker/harness/bin/bare)
 * run in a PHP process of its own - this one has loaded the bundle's tests -
 * builds the registry by hand, reads a product from a page through
 * omnitrade/web and asks a payment of omnitrade/paypal, from the answers kept
 * in docker/harness/recorded/, and reports every class PHP loaded on the way
 * and every file since the autoloader. None may be a framework's.
 */
final class BareTest extends TestCase
{
    private const FRAMEWORK = '~^(?:Symfony\\\\Component\\\\(?:DependencyInjection|Config|HttpKernel|HttpFoundation)|Symfony\\\\Bundle|Symfony\\\\Bridge|Doctrine|Twig)\\\\~';
    private const FRAMEWORK_FILES = '~/vendor/(?:symfony/(?:dependency-injection|config|http-kernel|http-foundation|[a-z-]*bundle|[a-z-]*bridge)|doctrine|twig)/~';

    public function testTheRegistryIsBuiltByHandAndNoClassOfAFrameworkIsLoaded(): void
    {
        [$status, $report] = self::php([__DIR__.'/../docker/harness/bin/bare', '--recorded', '--json']);

        self::assertSame(0, $status);
        self::assertContains(Registry::class, $report['symbols'], 'the registry was built there');
        self::assertSame([], self::framework($report), 'no class nor file of a framework');
        $installed = array_values(array_filter(array_column(require __DIR__.'/../docker/harness/plugins.php', 1), 'class_exists'));
        foreach ($installed as $factory) {
            self::assertContains($factory, $report['symbols'], 'every provider package installed, its factory built');
        }
        self::assertCount(\count($installed), $report['factories']);
    }

    public function testAProductIsReadFromARecordedPageWithNoClassOfAFrameworkLoaded(): void
    {
        if (!class_exists(WebGatewayFactory::class)) {
            self::markTestSkipped('omnitrade/web is not installed.');
        }
        [$status, $report] = self::php([__DIR__.'/../docker/harness/bin/bare', '--recorded', '--json']);

        self::assertSame(0, $status);
        self::assertSame(['product', 'affiliate'], $report['gateways']['web']);
        self::assertSame([
            'page' => 'https://boutique.example/p/poussette-yoyo',
            'reference' => 'https://boutique.example/p/poussette-yoyo',
            'title' => 'Poussette Yoyo³ & nacelle',
            'brand' => 'Stokke',
            'merchant' => 'Boutique SAS',
            'price' => '449.90',
            'currency' => 'EUR',
            'barcode' => '8716134000123',
            'media' => 2,
            'read_from' => 'json-ld',
        ], $report['product']);
        self::assertSame([], self::framework($report), 'no class nor file of a framework');
    }

    public function testAPaymentIsAskedOfRecordedAnswersWithNoClassOfAFrameworkLoaded(): void
    {
        if (!class_exists(PayPalGatewayFactory::class)) {
            self::markTestSkipped('omnitrade/paypal is not installed.');
        }
        [$status, $report] = self::php([__DIR__.'/../docker/harness/bin/bare', '--recorded', '--json']);

        self::assertSame(0, $status);
        self::assertTrue($report['recorded']);
        self::assertSame(['purchase', 'authorize', 'capture', 'refund', 'fetch', 'methods', 'notify'], $report['gateways']['paypal']);
        self::assertSame(['provider' => 'paypal', 'reference' => '5O190127TN364715T', 'status' => 'pending', 'redirect' => 'https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T', 'is_redirect' => true], $report['payment']);
        self::assertContains('Symfony\\Component\\HttpClient\\MockHttpClient', $report['symbols'], 'the answers came through the HTTP client given');
        self::assertSame([], self::framework($report), 'no class nor file of a framework');
    }

    /** The check is not blind: the same report, once the bundle is loaded, names the framework. */
    public function testTheBundleDoesLoadTheFramework(): void
    {
        if (!class_exists(AbstractBundle::class)) {
            self::markTestSkipped('symfony/http-kernel is not installed.');
        }
        [$status, $report] = self::php(['-r', 'require getenv("OMNITRADE_AUTOLOAD"); $autoloaded = get_included_files(); class_exists($argv[1]) || exit(2); echo json_encode(["symbols" => [...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()], "files" => array_values(array_diff(get_included_files(), $autoloaded))]);', '--', OmnitradeBundle::class]);

        self::assertSame(0, $status);
        $framework = self::framework($report);
        self::assertContains(AbstractBundle::class, $framework);
        self::assertNotEmpty(preg_grep('~/symfony/http-kernel/~', $framework));
    }

    /**
     * @param array{symbols: list<string>, files: list<string>} $report
     *
     * @return list<string> the classes, interfaces, traits and files of a framework among those loaded
     */
    private static function framework(array $report): array
    {
        return [...array_values(preg_grep(self::FRAMEWORK, $report['symbols'])), ...array_values(preg_grep(self::FRAMEWORK_FILES, $report['files']))];
    }

    /**
     * Runs PHP apart, on the autoloader of this run.
     *
     * @param list<string> $arguments
     *
     * @return array{int, array<string, mixed>} the exit status, the JSON printed
     */
    private static function php(array $arguments): array
    {
        $autoload = \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2).'/autoload.php';
        $process = proc_open([\PHP_BINARY, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['OMNITRADE_AUTOLOAD' => $autoload] + getenv());
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        $report = json_decode($out, true);
        self::assertIsArray($report, 'PHP exited '.$status.': '.$err.$out);

        return [$status, $report];
    }
}
