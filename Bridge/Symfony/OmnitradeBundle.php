<?php

namespace Omnitrade\Bridge\Symfony;

use Omnitrade\GatewayFactoryInterface;
use Omnitrade\GatewayInterface;
use Omnitrade\PayPal\PayPalGatewayFactory;
use Omnitrade\Registry;
use Omnitrade\Shopify\ShopifyGatewayFactory;
use Omnitrade\Stripe\StripeGatewayFactory;
use Omnitrade\WooCommerce\WooCommerceGatewayFactory;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Omnitrade in a Symfony application: the provider packages installed
 * (omnitrade/stripe, omnitrade/paypal, omnitrade/shopify, omnitrade/woocommerce)
 * registered, the shop's gateways built from configuration, Omnitrade\Registry
 * autowired, and each gateway injectable by its name:
 *
 *     omnitrade:
 *         gateways:
 *             card:   { factory: stripe, options: { api_key: '%env(STRIPE_API_KEY)%', webhook_secret: '%env(STRIPE_WEBHOOK_SECRET)%' } }
 *             paypal: { factory: paypal, options: { client_id: '%env(PAYPAL_CLIENT_ID)%', secret: '%env(PAYPAL_SECRET)%', sandbox: true } }
 *
 *     public function __construct(GatewayInterface $card) {}
 *
 * An application's own factories (a GatewayFactoryInterface) are registered
 * too, autoconfigured.
 */
final class OmnitradeBundle extends AbstractBundle
{
    protected string $extensionAlias = 'omnitrade';

    /** The provider packages this bundle knows, registered when installed. */
    private const FACTORIES = [StripeGatewayFactory::class, PayPalGatewayFactory::class, ShopifyGatewayFactory::class, WooCommerceGatewayFactory::class];

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('gateways')
                    ->info('The shop\'s providers, by name: a factory (stripe, paypal, shopify, woocommerce...) and its options.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('factory')->isRequired()->cannotBeEmpty()->end()
                            ->variableNode('options')->defaultValue([])->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /** @param array{gateways: array<string, array{factory: string, options: array<string, mixed>}>} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(GatewayFactoryInterface::class)->addTag('omnitrade.gateway_factory');

        $services = $container->services();
        foreach (self::FACTORIES as $factory) {
            if (class_exists($factory) && is_subclass_of($factory, GatewayFactoryInterface::class)) {
                $services->set($factory)->args([service('http_client')->nullOnInvalid()])->tag('omnitrade.gateway_factory');
            }
        }

        $services->set(Registry::class)
            ->args([tagged_iterator('omnitrade.gateway_factory'), $config['gateways']])
            ->public();

        foreach (array_keys($config['gateways']) as $name) {
            $id = 'omnitrade.gateway.'.$name;
            $services->set($id, GatewayInterface::class)->factory([service(Registry::class), 'get'])->args([$name]);
            $builder->registerAliasForArgument($id, GatewayInterface::class, $name);
        }
    }
}
