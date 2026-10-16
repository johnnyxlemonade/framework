<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Client;

use Lemonade\Framework\Container\CompiledContainerPlan;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerBuilder;
use Lemonade\Framework\Container\ServiceLifetime;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Http\Client\CurlHttpClientServiceProvider;
use Lemonade\Framework\Http\Client\GuzzleHttpClientServiceProvider;
use Lemonade\Framework\Http\Client\SymfonyHttpClientServiceProvider;
use Lemonade\Framework\Http\Config\HttpClientConfig;
use Lemonade\Framework\Http\Config\HttpClientConfigDefinition;
use Lemonade\Framework\Http\Config\HttpClientConfigResolver;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use ReflectionProperty;

final class HttpClientServiceProviderTest extends TestCase
{
    public function testGuzzleProviderAliasesVendorContractsToThePsr18Singleton(): void
    {
        $plan = $this->plan(new GuzzleHttpClientServiceProvider());

        self::assertAlias($plan, \GuzzleHttp\Client::class);
        self::assertAlias($plan, \GuzzleHttp\ClientInterface::class);
        $this->assertCommonConfigBindings($plan);
    }

    public function testCurlProviderAliasesItsConcreteClientToThePsr18Singleton(): void
    {
        $plan = $this->plan(new CurlHttpClientServiceProvider());

        self::assertAlias($plan, 'Http\\Client\\Curl\\Client');
        $this->assertCommonConfigBindings($plan);
    }

    public function testSymfonyProviderAliasesItsPsr18AdapterToThePsr18Singleton(): void
    {
        $plan = $this->plan(new SymfonyHttpClientServiceProvider());

        self::assertAlias($plan, 'Symfony\\Component\\HttpClient\\Psr18Client');
        self::assertSame(
            ServiceLifetime::Singleton,
            $plan->definition('Symfony\\Contracts\\HttpClient\\HttpClientInterface')?->lifetime,
        );
        $this->assertCommonConfigBindings($plan);
    }

    #[RunInSeparateProcess]
    public function testSymfonyPsr18AdapterUsesTheCanonicalNativeClientSingleton(): void
    {
        if (
            interface_exists('Symfony\\Contracts\\HttpClient\\HttpClientInterface')
            || class_exists('Symfony\\Component\\HttpClient\\Psr18Client')
        ) {
            self::markTestSkipped('The isolated Symfony transport stub requires the optional package to be absent.');
        }

        require_once __DIR__ . '/Support/SymfonyHttpClientStub.php';

        $container = $this->configuredContainer();
        (new SymfonyHttpClientServiceProvider())->register($container);

        $nativeClient = $container->get('Symfony\\Contracts\\HttpClient\\HttpClientInterface');
        $psr18Client = $container->get(ClientInterface::class);
        $nativeClientProperty = new ReflectionProperty($psr18Client, 'nativeClient');

        self::assertSame(
            $nativeClient,
            $container->get('Symfony\\Contracts\\HttpClient\\HttpClientInterface'),
        );
        self::assertSame($nativeClient, $nativeClientProperty->getValue($psr18Client));
        self::assertSame(
            $psr18Client,
            $container->get('Symfony\\Component\\HttpClient\\Psr18Client'),
        );
    }

    /**
     * @param class-string<ServiceProviderInterface> $firstProviderClass
     * @param class-string<ServiceProviderInterface> $secondProviderClass
     */
    #[DataProvider('transportProviderPairs')]
    public function testRejectsASecondPsr18TransportProvider(
        string $firstProviderClass,
        string $secondProviderClass,
    ): void {
        $builder = new ContainerBuilder();
        (new $firstProviderClass())->register($builder);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'Cannot register %s because %s is already bound. Configure exactly one PSR-18 transport provider.',
            $secondProviderClass,
            ClientInterface::class,
        ));

        (new $secondProviderClass())->register($builder);
    }

    public function testGuzzleAliasesResolveTheConfiguredClientWithSharedIdentity(): void
    {
        $container = new Container();
        $registry = new ConfigDefinitionRegistry();
        $registry->addDefinition(
            HttpClientConfigDefinition::create()
                ->timeout(3)
                ->connectTimeout(1.5)
                ->verifySsl(false),
        );
        $container->singleton(ConfigDefinitionRegistry::class, $registry);
        (new GuzzleHttpClientServiceProvider())->register($container);

        $client = $container->get(ClientInterface::class);

        self::assertInstanceOf(\GuzzleHttp\Client::class, $client);
        self::assertSame($client, $container->get(\GuzzleHttp\Client::class));
        self::assertSame($client, $container->get(\GuzzleHttp\ClientInterface::class));
        self::assertSame(3.0, $container->get(HttpClientConfig::class)->timeout);
        self::assertSame(1.5, $container->get(HttpClientConfig::class)->connectTimeout);
        self::assertFalse($container->get(HttpClientConfig::class)->verifySsl);
    }

    private static function assertAlias(CompiledContainerPlan $plan, string $alias): void
    {
        self::assertTrue($plan->hasAlias($alias));
        self::assertSame(ClientInterface::class, $plan->canonicalId($alias));
    }

    private function assertCommonConfigBindings(CompiledContainerPlan $plan): void
    {
        foreach ([
            HttpClientConfig::class,
            ClientInterface::class,
        ] as $serviceId) {
            self::assertSame(ServiceLifetime::Singleton, $plan->definition($serviceId)?->lifetime);
        }

        self::assertFalse($plan->hasDefinition(HttpClientConfigResolver::class));
    }

    /**
     * @return array<string, array{
     *     class-string<ServiceProviderInterface>,
     *     class-string<ServiceProviderInterface>
     * }>
     */
    public static function transportProviderPairs(): array
    {
        return [
            'Guzzle then cURL' => [GuzzleHttpClientServiceProvider::class, CurlHttpClientServiceProvider::class],
            'Guzzle then Symfony' => [GuzzleHttpClientServiceProvider::class, SymfonyHttpClientServiceProvider::class],
            'cURL then Guzzle' => [CurlHttpClientServiceProvider::class, GuzzleHttpClientServiceProvider::class],
            'cURL then Symfony' => [CurlHttpClientServiceProvider::class, SymfonyHttpClientServiceProvider::class],
            'Symfony then Guzzle' => [SymfonyHttpClientServiceProvider::class, GuzzleHttpClientServiceProvider::class],
            'Symfony then cURL' => [SymfonyHttpClientServiceProvider::class, CurlHttpClientServiceProvider::class],
        ];
    }

    private function plan(ServiceProviderInterface $provider): CompiledContainerPlan
    {
        $builder = new ContainerBuilder();
        $provider->register($builder);

        return $builder->compile();
    }

    private function configuredContainer(): Container
    {
        $container = new Container();
        $container->singleton(ConfigDefinitionRegistry::class, new ConfigDefinitionRegistry());

        return $container;
    }
}
