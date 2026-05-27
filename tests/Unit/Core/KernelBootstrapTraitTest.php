<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Core\KernelBootstrapTrait;
use Lemonade\Framework\Core\ServiceProviderInterface;
use LogicException;
use PHPUnit\Framework\TestCase;

final class KernelBootstrapTraitTest extends TestCase
{
    public function testMissingProviderClassThrowsLogicException(): void
    {
        $container = new Container();
        $subject = new KernelBootstrapTraitHarness($container);
        $container->singleton(Config::class, new Config([
            'framework' => [
                'providers' => ['Definitely\\Missing\\Provider'],
            ],
        ]));

        $this->expectException(LogicException::class);
        $subject->commonProviderClasses();
    }

    public function testProviderWithoutInterfaceThrowsLogicException(): void
    {
        $container = new Container();
        $subject = new KernelBootstrapTraitHarness($container);
        $container->singleton(Config::class, new Config([
            'framework' => [
                'providers' => [NotAServiceProvider::class],
            ],
        ]));

        $this->expectException(LogicException::class);
        $subject->commonProviderClasses();
    }

    public function testValidProviderClassPassesValidation(): void
    {
        $container = new Container();
        $subject = new KernelBootstrapTraitHarness($container);
        $container->singleton(Config::class, new Config([
            'framework' => [
                'providers' => [ValidServiceProvider::class],
            ],
        ]));

        self::assertSame([ValidServiceProvider::class], $subject->commonProviderClasses());
    }
}

final class KernelBootstrapTraitHarness
{
    use KernelBootstrapTrait;

    public readonly Framework $framework;
    public readonly ApplicationContext $context;

    public function __construct(
        public readonly ContainerInterface $container,
    ) {
        $this->context = new ApplicationContext(
            Environment::Testing,
            new Path(__DIR__),
            DebugMode::disabled(),
        );
        $this->framework = new Framework($container, $this->context);
    }

    /**
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function commonProviderClasses(): array
    {
        return $this->commonFrameworkProviderClasses();
    }
}

final class NotAServiceProvider {}

final class ValidServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void {}
}
