<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Container;

use Lemonade\Framework\Container\Config\ContainerConfig;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\Exception\ContainerException;
use Lemonade\Framework\Container\Exception\ServiceNotFoundException;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

interface TestContractInterface
{
    public function id(): string;
}

final class TestContractImplementation implements TestContractInterface
{
    public function id(): string
    {
        return 'impl';
    }
}

final class NeedsInterfaceWithoutBinding
{
    public function __construct(
        public readonly TestContractInterface $dependency,
    ) {}
}

final class NeedsInterfaceWithBinding
{
    public function __construct(
        public readonly TestContractInterface $dependency,
    ) {}
}

final class PlainConcreteClass
{
    public function id(): string
    {
        return 'plain';
    }
}

abstract class NonInstantiableClass {}

final class DependencyClass
{
    public function value(): string
    {
        return 'dep';
    }
}

final class NeedsConcreteDependency
{
    public function __construct(
        public readonly DependencyClass $dependency,
    ) {}
}

final class NeedsDefaultScalarValue
{
    public function __construct(
        public readonly string $name = 'default-name',
    ) {}
}

final class SingletonDependency
{
    public function __construct(
        public readonly string $id = 'singleton-dependency',
    ) {}
}

final class NeedsSingletonDependency
{
    public function __construct(
        public readonly SingletonDependency $dependency,
    ) {}
}

final class InvokableFactory
{
    public function __invoke(): \stdClass
    {
        return new \stdClass();
    }
}

final class ContainerDiagnosticLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string, context: array<mixed>}>
     */
    public array $records = [];

    /**
     * @param array<mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}

final class ContainerTest extends TestCase
{
    public function testIsBoundReturnsTrueOnlyForExplicitBinding(): void
    {
        $container = new Container();
        $container->singleton('service.id', new \stdClass());

        self::assertTrue($container->isBound('service.id'));
        self::assertFalse($container->isBound(PlainConcreteClass::class));
    }

    public function testHasReturnsTrueForExplicitBinding(): void
    {
        $container = new Container();
        $container->singleton('service.id', new \stdClass());

        self::assertTrue($container->has('service.id'));
    }

    public function testHasReturnsTrueForExistingConcreteClassViaAutowiring(): void
    {
        $container = new Container();

        self::assertTrue($container->has(PlainConcreteClass::class));
    }

    public function testIsBoundReturnsFalseForExistingButUnboundConcreteClass(): void
    {
        $container = new Container();

        self::assertFalse($container->isBound(PlainConcreteClass::class));
    }

    public function testInterfaceDependencyWithoutBindingThrows(): void
    {
        $container = new Container();

        $this->expectException(ServiceNotFoundException::class);
        $container->get(NeedsInterfaceWithoutBinding::class);
    }

    public function testInterfaceDependencyWithBindingResolves(): void
    {
        $container = new Container();
        $container->singleton(TestContractInterface::class, TestContractImplementation::class);

        $resolved = $container->get(NeedsInterfaceWithBinding::class);

        self::assertInstanceOf(NeedsInterfaceWithBinding::class, $resolved);
        self::assertInstanceOf(TestContractImplementation::class, $resolved->dependency);
    }

    public function testSetReturnsNewInstanceOnEachGetWhenFactoryCreatesNewObject(): void
    {
        $container = new Container();
        $container->set('factory.service', static fn(): \stdClass => new \stdClass());

        $first = $container->get('factory.service');
        $second = $container->get('factory.service');

        self::assertNotSame($first, $second);
    }

    public function testSingletonReturnsSameInstanceOnRepeatedGet(): void
    {
        $container = new Container();
        $container->singleton('singleton.service', static fn(): \stdClass => new \stdClass());

        $first = $container->get('singleton.service');
        $second = $container->get('singleton.service');

        self::assertSame($first, $second);
    }

    public function testSingletonWithObjectInstanceReturnsSameObject(): void
    {
        $container = new Container();
        $instance = new \stdClass();
        $container->singleton('singleton.object', $instance);

        self::assertSame($instance, $container->get('singleton.object'));
        self::assertSame($instance, $container->get('singleton.object'));
    }

    public function testSetAfterExistingSingletonInvalidatesStoredInstance(): void
    {
        $container = new Container();
        $container->singleton('service', static fn(): \stdClass => new \stdClass());

        $first = $container->get('service');
        $container->set('service', static fn(): \stdClass => new \stdClass());
        $second = $container->get('service');

        self::assertNotSame($first, $second);
    }

    public function testGetForMissingServiceThrowsServiceNotFoundException(): void
    {
        $container = new Container();

        $this->expectException(ServiceNotFoundException::class);
        $container->get('missing.service');
    }

    public function testGetForNonInstantiableClassThrowsContainerException(): void
    {
        $container = new Container();

        $this->expectException(ContainerException::class);
        $container->get(NonInstantiableClass::class);
    }

    public function testConcreteConstructorDependencyIsAutowired(): void
    {
        $container = new Container();

        $resolved = $container->get(NeedsConcreteDependency::class);

        self::assertInstanceOf(NeedsConcreteDependency::class, $resolved);
        self::assertInstanceOf(DependencyClass::class, $resolved->dependency);
        self::assertSame('dep', $resolved->dependency->value());
    }

    public function testClassStringBindingResolvesConcreteImplementation(): void
    {
        $container = new Container();
        $container->singleton('plain.service', PlainConcreteClass::class);

        $resolved = $container->get('plain.service');

        self::assertInstanceOf(PlainConcreteClass::class, $resolved);
    }

    public function testInvokableFactoryBindingReturnsNewInstancePerGet(): void
    {
        $container = new Container();
        $container->set('invokable.factory', new InvokableFactory());

        $first = $container->get('invokable.factory');
        $second = $container->get('invokable.factory');

        self::assertInstanceOf(\stdClass::class, $first);
        self::assertInstanceOf(\stdClass::class, $second);
        self::assertNotSame($first, $second);
    }

    public function testDefaultConstructorValueIsUsedForUnresolvableBuiltinParameter(): void
    {
        $container = new Container();

        $resolved = $container->get(NeedsDefaultScalarValue::class);

        self::assertSame('default-name', $resolved->name);
    }

    public function testAutowiringPlanIsReusedAcrossRepeatedBuilds(): void
    {
        $container = new Container();

        $container->get(NeedsConcreteDependency::class);
        $firstPlanCount = count($this->buildPlans($container));

        $container->get(NeedsConcreteDependency::class);
        $secondPlanCount = count($this->buildPlans($container));

        self::assertSame($firstPlanCount, $secondPlanCount);
        self::assertArrayHasKey(NeedsConcreteDependency::class, $this->buildPlans($container));
        self::assertArrayHasKey(DependencyClass::class, $this->buildPlans($container));
    }

    public function testSingletonDependencyIsReusedAcrossTransientConsumers(): void
    {
        $container = new Container();
        $container->singleton(SingletonDependency::class, SingletonDependency::class);

        $first = $container->get(NeedsSingletonDependency::class);
        $second = $container->get(NeedsSingletonDependency::class);

        self::assertNotSame($first, $second);
        self::assertSame($first->dependency, $second->dependency);
    }

    public function testCircularDependencyThrowsWithFullDependencyChain(): void
    {
        $container = new Container();

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            'Circular dependency detected: Lemonade\Framework\Tests\Unit\Container\CircularDependencyA -> Lemonade\Framework\Tests\Unit\Container\CircularDependencyB -> Lemonade\Framework\Tests\Unit\Container\CircularDependencyA',
        );

        $container->get(CircularDependencyA::class);
    }

    public function testAppServiceAutowireFallbackWarningIsLogged(): void
    {
        $logger = new ContainerDiagnosticLogger();
        $container = $this->diagnosticContainer($logger);

        $container->get(\App\Services\ContainerAutowireFallbackService::class);

        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertSame(\App\Services\ContainerAutowireFallbackService::class, $logger->records[0]['context']['service']);
        self::assertSame('container.autowire_fallback', $logger->records[0]['context']['source']);
        self::assertStringContainsString('App\\Providers\\AppServiceProvider', $logger->records[0]['message']);
    }

    public function testFrameworkManagerAutowireFallbackWarningIsLogged(): void
    {
        $logger = new ContainerDiagnosticLogger();
        $container = $this->diagnosticContainer($logger);

        $container->get(\Lemonade\Framework\Tests\Unit\Container\Fixtures\ContainerDiagnosticManager::class);

        self::assertCount(1, $logger->records);
        self::assertSame(\Lemonade\Framework\Tests\Unit\Container\Fixtures\ContainerDiagnosticManager::class, $logger->records[0]['context']['service']);
        self::assertStringContainsString('appropriate framework ServiceProvider', $logger->records[0]['message']);
    }

    public function testFrameworkRegistryAutowireFallbackWarningIsLogged(): void
    {
        $logger = new ContainerDiagnosticLogger();
        $container = $this->diagnosticContainer($logger);

        $container->get(\Lemonade\Framework\Tests\Unit\Container\Fixtures\ContainerDiagnosticRegistry::class);

        self::assertCount(1, $logger->records);
        self::assertSame(\Lemonade\Framework\Tests\Unit\Container\Fixtures\ContainerDiagnosticRegistry::class, $logger->records[0]['context']['service']);
    }

    public function testFrameworkFactoryAutowireFallbackDoesNotWarn(): void
    {
        $logger = new ContainerDiagnosticLogger();
        $container = $this->diagnosticContainer($logger);

        $container->get(\Lemonade\Framework\Tests\Unit\Container\Fixtures\ContainerDiagnosticFactory::class);

        self::assertSame([], $logger->records);
    }

    public function testFrameworkResolverAutowireFallbackDoesNotWarn(): void
    {
        $logger = new ContainerDiagnosticLogger();
        $container = $this->diagnosticContainer($logger);

        $container->get(\Lemonade\Framework\Tests\Unit\Container\Fixtures\ContainerDiagnosticResolver::class);

        self::assertSame([], $logger->records);
    }

    public function testFrameworkMiddlewareAutowireFallbackWarningIsLogged(): void
    {
        $logger = new ContainerDiagnosticLogger();
        $container = $this->diagnosticContainer($logger);

        $container->get(\Lemonade\Framework\Tests\Unit\Container\Fixtures\ContainerDiagnosticMiddleware::class);

        self::assertCount(1, $logger->records);
        self::assertSame(\Lemonade\Framework\Tests\Unit\Container\Fixtures\ContainerDiagnosticMiddleware::class, $logger->records[0]['context']['service']);
    }

    public function testVendorMiddlewareAutowireFallbackDoesNotWarn(): void
    {
        $logger = new ContainerDiagnosticLogger();
        $container = $this->diagnosticContainer($logger);

        $container->get(\Vendor\Package\ContainerExternalMiddleware::class);

        self::assertSame([], $logger->records);
    }

    public function testVendorClassAutowireFallbackDoesNotWarn(): void
    {
        $logger = new ContainerDiagnosticLogger();
        $container = $this->diagnosticContainer($logger);

        $container->get(\Vendor\Package\ContainerExternalService::class);

        self::assertSame([], $logger->records);
    }

    public function testAutowireFallbackWarningIsLoggedOnlyOncePerServiceId(): void
    {
        $logger = new ContainerDiagnosticLogger();
        $container = $this->diagnosticContainer($logger);

        $container->get(\App\Services\ContainerAutowireFallbackService::class);
        $container->get(\App\Services\ContainerAutowireFallbackService::class);

        self::assertCount(1, $logger->records);
    }

    public function testAppModelAndAuthenticatorAutowireFallbackWarningsAreLogged(): void
    {
        $logger = new ContainerDiagnosticLogger();
        $container = $this->diagnosticContainer($logger);

        $container->get(\App\Models\ContainerAutowireFallbackModel::class);
        $container->get(\App\Auth\ContainerAutowireFallbackAuthenticator::class);

        self::assertCount(2, $logger->records);
        self::assertSame(\App\Models\ContainerAutowireFallbackModel::class, $logger->records[0]['context']['service']);
        self::assertSame(\App\Auth\ContainerAutowireFallbackAuthenticator::class, $logger->records[1]['context']['service']);
    }

    public function testAutowireFallbackWarningCanBeDisabledByConfig(): void
    {
        $logger = new ContainerDiagnosticLogger();
        $container = new Container();
        $container->singleton(ContainerConfig::class, new ContainerConfig(false));
        $container->setDiagnosticLogger($logger);

        $container->get(\App\Services\ContainerAutowireFallbackService::class);

        self::assertSame([], $logger->records);
    }

    public function testProductionFallbackSkipsReportabilityWorkWhenWarningsAreDisabled(): void
    {
        $container = new Container();
        $container->singleton(ContainerConfig::class, new ContainerConfig(false));

        $container->get(\App\Services\ContainerAutowireFallbackService::class);

        self::assertSame([], $this->autowireFallbackReportable($container));
    }

    public function testDevelopmentFallbackCachesReportabilityDecision(): void
    {
        $container = $this->diagnosticContainer(new ContainerDiagnosticLogger());

        $container->get(\App\Services\ContainerAutowireFallbackService::class);

        $reportable = $this->autowireFallbackReportable($container);

        self::assertArrayHasKey(\App\Services\ContainerAutowireFallbackService::class, $reportable);
        self::assertTrue($reportable[\App\Services\ContainerAutowireFallbackService::class]);
    }

    public function testMissingClassLookupCachesNegativeExistenceResult(): void
    {
        $container = new Container();

        try {
            $container->get('Missing\\ClassName');
            self::fail('Expected missing service exception.');
        } catch (ServiceNotFoundException $exception) {
            self::assertSame('Service "Missing\\ClassName" was not found.', $exception->getMessage());
        }

        $cache = $this->classExistenceCache($container);

        self::assertArrayHasKey('Missing\\ClassName', $cache);
        self::assertFalse($cache['Missing\\ClassName']);

        try {
            $container->get('Missing\\ClassName');
            self::fail('Expected missing service exception.');
        } catch (ServiceNotFoundException $exception) {
            self::assertSame('Service "Missing\\ClassName" was not found.', $exception->getMessage());
        }

        self::assertSame($cache, $this->classExistenceCache($container));
    }

    public function testMissingInterfaceLookupCachesNegativeExistenceResult(): void
    {
        $container = new Container();
        $method = new \ReflectionMethod($container, 'interfaceExists');

        self::assertFalse($method->invoke($container, 'Missing\\InterfaceName'));

        $cache = $this->interfaceExistenceCache($container);

        self::assertArrayHasKey('Missing\\InterfaceName', $cache);
        self::assertFalse($cache['Missing\\InterfaceName']);
        self::assertFalse($method->invoke($container, 'Missing\\InterfaceName'));
        self::assertSame($cache, $this->interfaceExistenceCache($container));
    }

    public function testExistingClassAndInterfaceExistenceCachesStayFunctional(): void
    {
        $container = new Container();
        $classMethod = new \ReflectionMethod($container, 'classExists');
        $interfaceMethod = new \ReflectionMethod($container, 'interfaceExists');

        self::assertTrue($classMethod->invoke($container, PlainConcreteClass::class));
        self::assertTrue($interfaceMethod->invoke($container, TestContractInterface::class));

        $classCache = $this->classExistenceCache($container);
        $interfaceCache = $this->interfaceExistenceCache($container);

        self::assertTrue($classCache[PlainConcreteClass::class]);
        self::assertTrue($interfaceCache[TestContractInterface::class]);
    }

    public function testAutowireFallbackReportabilityCachesFalseDecision(): void
    {
        $container = new Container();
        $method = new \ReflectionMethod($container, 'shouldReportAutowireFallback');

        self::assertFalse($method->invoke($container, \Vendor\Package\ContainerExternalService::class));

        $cache = $this->autowireFallbackReportable($container);

        self::assertArrayHasKey(\Vendor\Package\ContainerExternalService::class, $cache);
        self::assertFalse($cache[\Vendor\Package\ContainerExternalService::class]);
        self::assertFalse($method->invoke($container, \Vendor\Package\ContainerExternalService::class));
        self::assertSame($cache, $this->autowireFallbackReportable($container));
    }

    public function testAutowireFallbackUsesErrorLogOnlyWhenLoggerIsUnavailable(): void
    {
        $errorLog = tempnam(sys_get_temp_dir(), 'lemonade-container-error-log-');
        self::assertIsString($errorLog);

        $previousErrorLog = ini_get('error_log');
        $previousLogErrors = ini_get('log_errors');
        ini_set('error_log', $errorLog);
        ini_set('log_errors', '1');

        try {
            $container = new Container();
            $container->singleton(ApplicationContext::class, $this->developmentContext());

            $container->get(\App\Services\ContainerAutowireFallbackService::class);

            $contents = file_get_contents($errorLog);
            self::assertIsString($contents);
            self::assertStringContainsString('[Lemonade][Container]', $contents);
            self::assertStringContainsString(\App\Services\ContainerAutowireFallbackService::class, $contents);
        } finally {
            ini_set('error_log', is_string($previousErrorLog) ? $previousErrorLog : '');
            ini_set('log_errors', is_string($previousLogErrors) ? $previousLogErrors : '1');
            @unlink($errorLog);
        }
    }

    public function testAutowireFallbackDoesNotUseErrorLogWhenLoggerIsAvailable(): void
    {
        $errorLog = tempnam(sys_get_temp_dir(), 'lemonade-container-error-log-');
        self::assertIsString($errorLog);

        $previousErrorLog = ini_get('error_log');
        $previousLogErrors = ini_get('log_errors');
        ini_set('error_log', $errorLog);
        ini_set('log_errors', '1');

        try {
            $logger = new ContainerDiagnosticLogger();
            $container = $this->diagnosticContainer($logger);

            $container->get(\App\Services\ContainerAutowireFallbackService::class);

            self::assertCount(1, $logger->records);
            self::assertSame('', (string) file_get_contents($errorLog));
        } finally {
            ini_set('error_log', is_string($previousErrorLog) ? $previousErrorLog : '');
            ini_set('log_errors', is_string($previousLogErrors) ? $previousLogErrors : '1');
            @unlink($errorLog);
        }
    }

    private function diagnosticContainer(ContainerDiagnosticLogger $logger): Container
    {
        $container = new Container();
        $container->singleton(ContainerConfig::class, new ContainerConfig(true));
        $container->setDiagnosticLogger($logger);

        return $container;
    }

    private function developmentContext(): ApplicationContext
    {
        return new ApplicationContext(
            Environment::Development,
            new Path(sys_get_temp_dir()),
            DebugMode::enabled(),
        );
    }

    /**
     * @return array<class-string, mixed>
     */
    private function buildPlans(Container $container): array
    {
        $property = new \ReflectionProperty($container, 'buildPlans');

        /** @var array<class-string, mixed> $plans */
        $plans = $property->getValue($container);

        return $plans;
    }

    /**
     * @return array<string, bool>
     */
    private function autowireFallbackReportable(Container $container): array
    {
        $property = new \ReflectionProperty($container, 'autowireFallbackReportable');

        /** @var array<string, bool> $reportable */
        $reportable = $property->getValue($container);

        return $reportable;
    }

    /**
     * @return array<string, bool>
     */
    private function classExistenceCache(Container $container): array
    {
        $property = new \ReflectionProperty($container, 'classExistenceCache');

        /** @var array<string, bool> $cache */
        $cache = $property->getValue($container);

        return $cache;
    }

    /**
     * @return array<string, bool>
     */
    private function interfaceExistenceCache(Container $container): array
    {
        $property = new \ReflectionProperty($container, 'interfaceExistenceCache');

        /** @var array<string, bool> $cache */
        $cache = $property->getValue($container);

        return $cache;
    }
}

final class CircularDependencyA
{
    public function __construct(
        public readonly CircularDependencyB $dependency,
    ) {}
}

final class CircularDependencyB
{
    public function __construct(
        public readonly CircularDependencyA $dependency,
    ) {}
}

namespace App\Services;

final class ContainerAutowireFallbackService {}

namespace Vendor\Package;

final class ContainerExternalService {}

final class ContainerExternalMiddleware {}

namespace Lemonade\Framework\Tests\Unit\Container\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ContainerDiagnosticManager {}

final class ContainerDiagnosticRegistry {}

final class ContainerDiagnosticFactory {}

final class ContainerDiagnosticResolver {}

final class ContainerDiagnosticMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}

namespace App\Models;

final class ContainerAutowireFallbackModel {}

namespace App\Auth;

final class ContainerAutowireFallbackAuthenticator {}
