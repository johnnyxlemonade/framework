<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Support;

use Lemonade\Framework\Debug\Dump\Contract\DumperInterface;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RemovedGlobalServiceHelpersTest extends TestCase
{
    public function testServiceHelperThrowsClearException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The global service() helper has been removed.');

        service('validator');
    }

    /**
     * @param callable(): mixed $call
     */
    #[DataProvider('removedServiceBackedHelpers')]
    public function testRemovedServiceBackedHelpersThrowClearException(callable $call): void
    {
        $this->expectException(LogicException::class);

        $call();
    }

    /**
     * @return iterable<string, array{callable(): mixed}>
     */
    public static function removedServiceBackedHelpers(): iterable
    {
        yield 'asset' => [static fn (): string => asset('css/app.css')];
        yield 'event' => [static fn (): object => event((object) [])];
        yield 'queue' => [static function (): mixed {
            queue((object) []);

            return null;
        }];
        yield 'config' => [static fn (): mixed => config('app.name')];
        yield 'csrf_field' => [static fn (): string => csrf_field()];
        yield 'csrf_token' => [static fn (): string => csrf_token()];
        yield 'flash' => [static fn (): mixed => flash('notice')];
        yield 'lang' => [static fn (): string => lang('messages.hello')];
        yield 'current_locale' => [static fn (): string => current_locale()];
        yield 'lang_group' => [static fn (): array => lang_group('messages')];
        yield 'lang_all' => [static fn (): array => lang_all()];
        yield 'old' => [static fn (): mixed => old('email')];
        yield 'base_path' => [static fn (): string => base_path()];
        yield 'app_path' => [static fn (): string => app_path()];
        yield 'storage_path' => [static fn (): string => storage_path()];
        yield 'url' => [static fn (): string => url('home')];
        yield 'localized_url' => [static fn (): string => localized_url('home')];
        yield 'current_path' => [static fn (): string => current_path()];
        yield 'current_query' => [static fn (): string => current_query()];
        yield 'current_url' => [static fn (): string => current_url()];
        yield 'current_full_url' => [static fn (): string => current_full_url()];
        yield 'is_url_active' => [static fn (): bool => is_url_active('/')];
        yield 'is_route_active' => [static fn (): bool => is_route_active('home')];
    }

    public function testDumperRemainsAvailableWithoutContainerRuntime(): void
    {
        self::assertInstanceOf(DumperInterface::class, dumper());
    }

    public function testLoggerReturnsNullLoggerWithoutContainerRuntime(): void
    {
        self::assertInstanceOf(LoggerInterface::class, logger());
    }
}
