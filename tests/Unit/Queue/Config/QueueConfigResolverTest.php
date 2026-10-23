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
        self::assertSame(300, $config->database->leaseSeconds);
        self::assertSame(3, $config->database->maxAttempts);
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

    public function testDatabaseLeasePolicyCanBeConfigured(): void
    {
        $config = (new QueueConfigResolver())->resolve(
            QueueConfigDefinition::create()
                ->databaseLeaseSeconds(45)
                ->databaseMaxAttempts(7),
        );

        self::assertSame(45, $config->database->leaseSeconds);
        self::assertSame(7, $config->database->maxAttempts);
    }

    public function testNonPositiveLeaseIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('lease_seconds must be a positive integer');

        (new QueueConfigResolver())->resolve(
            QueueConfigDefinition::create()->databaseLeaseSeconds(0),
        );
    }

    public function testMalformedMaxAttemptsIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('max_attempts must be a positive integer');

        (new QueueConfigResolver())->resolve(
            QueueConfigDefinition::fromArrayData([
                'database' => [
                    'max_attempts' => 'invalid',
                ],
            ]),
        );
    }
}
