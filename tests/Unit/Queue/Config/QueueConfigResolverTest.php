<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Queue\Config;

use InvalidArgumentException;
use Lemonade\Framework\Queue\Config\QueueConfigDefinition;
use Lemonade\Framework\Queue\Config\QueueConfigResolver;
use PHPUnit\Framework\TestCase;

final class QueueConfigResolverTest extends TestCase
{
    public function testZeroConfigurationUsesSynchronousTransport(): void
    {
        $config = (new QueueConfigResolver())->resolve();

        self::assertSame('sync', $config->defaultTransport);
        self::assertSame(['sync'], $config->transports);
    }

    public function testSupportedDatabaseDefaultMustBePresentAndResolves(): void
    {
        $config = (new QueueConfigResolver())->resolve(
            QueueConfigDefinition::create()
                ->defaultTransport('database')
                ->transports(['sync', 'database']),
        );

        self::assertSame('database', $config->defaultTransport);
        self::assertSame(['sync', 'database'], $config->transports);
    }

    public function testUnknownConfiguredTransportIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Queue transport "rabbitmq" is unsupported');

        (new QueueConfigResolver())->resolve(
            QueueConfigDefinition::create()->transports(['sync', 'rabbitmq']),
        );
    }

    public function testUnknownDefaultTransportIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Default queue transport "databse" is unsupported');

        (new QueueConfigResolver())->resolve(
            QueueConfigDefinition::create()
                ->defaultTransport('databse')
                ->transports(['sync']),
        );
    }

    public function testDefaultTransportMustExistInConfiguredSet(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Default queue transport "database" is not present in the configured transports.',
        );

        (new QueueConfigResolver())->resolve(
            QueueConfigDefinition::create()
                ->defaultTransport('database')
                ->transports(['sync']),
        );
    }

    public function testExplicitEmptyTransportListDoesNotFallBackToSync(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Default queue transport "sync" is not present in the configured transports.',
        );

        (new QueueConfigResolver())->resolve(
            QueueConfigDefinition::create()->transports([]),
        );
    }

    public function testExplicitBlankDefaultDoesNotFallBackToSync(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Default queue transport must be a non-empty string');

        (new QueueConfigResolver())->resolve(
            QueueConfigDefinition::create()->defaultTransport('   '),
        );
    }

    public function testMalformedTransportListEntryIsRejectedInsteadOfIgnored(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Queue transport must be a non-empty string');

        (new QueueConfigResolver())->resolve(
            QueueConfigDefinition::fromArrayData([
                'transports' => ['sync', null],
            ]),
        );
    }
}
