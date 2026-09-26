<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Container;

use Lemonade\Framework\Component\Pagination\Config\PaginationConfigDefinition;
use Lemonade\Framework\Component\Pagination\PaginationFactory;
use Lemonade\Framework\Component\Pagination\PaginationServiceProvider;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\Exception\ScopedServiceRequestedFromRootException;
use Lemonade\Framework\Container\ScopeKind;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Config\UploadConfigDefinition;
use Lemonade\Framework\Upload\UploadFactory;
use Lemonade\Framework\Upload\UploadService;
use Lemonade\Framework\Upload\UploadServiceProvider;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class RequestScopedFactoryLifetimeTest extends TestCase
{
    public function testPaginationFactoryResolvesOnlyFromRequestScopeAndUsesItsRequest(): void
    {
        $container = new Container();
        $definitions = new ConfigDefinitionRegistry();
        $definitions->addDefinition(
            PaginationConfigDefinition::create()
                ->defaultPerPage(20)
                ->maxPerPage(100)
                ->visiblePages(7)
                ->showFirstLast(),
        );
        $container->singleton(ConfigDefinitionRegistry::class, $definitions);
        (new PaginationServiceProvider())->register($container);

        $this->expectException(ScopedServiceRequestedFromRootException::class);
        $container->get(PaginationFactory::class);
    }

    public function testPaginationFactoryIsScopedAndDoesNotShareTheRequestBetweenScopes(): void
    {
        $container = new Container();
        $definitions = new ConfigDefinitionRegistry();
        $definitions->addDefinition(PaginationConfigDefinition::create());
        $container->singleton(ConfigDefinitionRegistry::class, $definitions);
        (new PaginationServiceProvider())->register($container);

        $firstScope = $container->beginScope(ScopeKind::Request);
        $secondScope = $container->beginScope(ScopeKind::Request);
        $firstScope->bindScopedInstance(ServerRequestInterface::class, new ServerRequest('GET', '/first?filter=one&page=2'));
        $secondScope->bindScopedInstance(ServerRequestInterface::class, new ServerRequest('GET', '/second?filter=two&page=3'));

        try {
            $first = $firstScope->get(PaginationFactory::class);
            $second = $secondScope->get(PaginationFactory::class);

            self::assertSame($first, $firstScope->get(PaginationFactory::class));
            self::assertNotSame($first, $second);
            self::assertSame('/first?filter=one&page=2', $first->fromArray([['id' => 1]])->state()->url(2));
            self::assertSame('/second?filter=two&page=2', $second->fromArray([['id' => 1]])->state()->url(2));
        } finally {
            $firstScope->close();
            $secondScope->close();
        }
    }

    public function testUploadFactoryResolvesOnlyFromRequestScope(): void
    {
        $container = $this->uploadContainer();

        $this->expectException(ScopedServiceRequestedFromRootException::class);
        $container->get(UploadFactory::class);
    }

    public function testUploadFactoryIsScopedAndUsesTheRequestBoundToEachScope(): void
    {
        $container = $this->uploadContainer();
        $firstRequest = new ServerRequest('POST', '/first-upload');
        $secondRequest = new ServerRequest('POST', '/second-upload');
        $firstScope = $container->beginScope(ScopeKind::Request);
        $secondScope = $container->beginScope(ScopeKind::Request);
        $firstScope->bindScopedInstance(ServerRequestInterface::class, $firstRequest);
        $secondScope->bindScopedInstance(ServerRequestInterface::class, $secondRequest);

        try {
            $first = $firstScope->get(UploadFactory::class);
            $second = $secondScope->get(UploadFactory::class);
            $requestProperty = new \ReflectionProperty(UploadFactory::class, 'request');

            self::assertSame($first, $firstScope->get(UploadFactory::class));
            self::assertNotSame($first, $second);
            self::assertSame($firstRequest, $requestProperty->getValue($first));
            self::assertSame($secondRequest, $requestProperty->getValue($second));
        } finally {
            $firstScope->close();
            $secondScope->close();
        }
    }

    private function uploadContainer(): Container
    {
        $container = new Container();
        $definitions = new ConfigDefinitionRegistry();
        $definitions->addDefinition(UploadConfigDefinition::create());
        $container->singleton(ConfigDefinitionRegistry::class, $definitions);
        $container->singleton(TranslatorInterface::class, new RequestScopedFactoryTranslator());
        $container->singleton(ApplicationContext::class, new ApplicationContext(
            Environment::Testing,
            new Path('/var/www/framework', '/var/www/framework/public'),
            DebugMode::disabled(),
        ));
        (new UploadServiceProvider())->register($container);
        $container->singleton(
            UploadService::class,
            (new \ReflectionClass(UploadService::class))->newInstanceWithoutConstructor(),
        );

        return $container;
    }
}

final class RequestScopedFactoryTranslator implements TranslatorInterface
{
    public function setLocale(?string $locale): self
    {
        unset($locale);

        return $this;
    }

    public function locale(): ?string
    {
        return null;
    }

    public function get(string $key, array $replacements = [], ?string $locale = null): string
    {
        unset($replacements, $locale);

        return $key;
    }

    public function group(string $group, ?string $locale = null): array
    {
        unset($locale);

        return [$group => $group];
    }

    public function all(?string $locale = null): array
    {
        unset($locale);

        return [];
    }
}
