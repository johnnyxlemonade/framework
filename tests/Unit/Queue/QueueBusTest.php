<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Queue;

use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Cli\ConsoleServiceProvider;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\Exception\ServiceNotFoundException;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Queue\Cli\QueueInstallCommand;
use Lemonade\Framework\Queue\Cli\QueueWorkCommand;
use Lemonade\Framework\Queue\Config\QueueConfig;
use Lemonade\Framework\Queue\Config\QueueDatabaseConfig;
use Lemonade\Framework\Queue\Exception\QueueHandlerFailureException;
use Lemonade\Framework\Queue\Exception\QueueLeaseLostException;
use Lemonade\Framework\Queue\JobContext;
use Lemonade\Framework\Queue\JobHandlerRegistry;
use Lemonade\Framework\Queue\QueuedMessage;
use Lemonade\Framework\Queue\QueueBus;
use Lemonade\Framework\Queue\QueueBusInterface;
use Lemonade\Framework\Queue\QueueLeaseInterface;
use Lemonade\Framework\Queue\QueueServiceProvider;
use Lemonade\Framework\Queue\QueueTransportInterface;
use Lemonade\Framework\Queue\RenewableQueueTransportInterface;
use Lemonade\Framework\Queue\Transport\SyncQueueTransport;
use PHPUnit\Framework\TestCase;

final class QueueBusTest extends TestCase
{
    public function testHandlerRegistryPrefersConcreteMessageClass(): void
    {
        $registry = new JobHandlerRegistry();
        $parentHandler = static function (): void {};
        $concreteHandler = static function (): void {};
        $registry->register(QueueBusParentMessage::class, $parentHandler);
        $registry->register(QueueBusMessage::class, $concreteHandler);

        self::assertSame($concreteHandler, $registry->handlerFor(new QueueBusMessage()));
    }

    public function testHandlerRegistryFallsBackToParentClassThenInterface(): void
    {
        $registry = new JobHandlerRegistry();
        $parentHandler = static function (): void {};
        $interfaceHandler = static function (): void {};
        $registry->register(QueueBusParentMessage::class, $parentHandler);
        $registry->register(QueueBusMessageContract::class, $interfaceHandler);

        self::assertSame($parentHandler, $registry->handlerFor(new QueueBusMessage()));
        self::assertSame($interfaceHandler, $registry->handlerFor(new QueueBusInterfaceOnlyMessage()));
    }

    public function testSyncDispatchResolvesClassStringHandler(): void
    {
        QueueBusClassStringHandler::$handled = 0;
        $bus = $this->bus();
        $bus->addHandler(QueueBusMessage::class, QueueBusClassStringHandler::class);

        $bus->dispatch(new QueueBusMessage(), transport: 'sync');

        self::assertSame(1, QueueBusClassStringHandler::$handled);
    }

    public function testSyncDispatchResolvesLegacyCallableHandler(): void
    {
        $handled = [];
        $bus = $this->bus();
        $bus->addHandler(QueueBusMessage::class, static function (object $message) use (&$handled): void {
            $handled[] = $message;
        });

        $message = new QueueBusMessage();
        $bus->dispatch($message, transport: 'sync');

        self::assertSame([$message], $handled);
    }

    public function testAsyncDispatchHandsMessageToConfiguredTransport(): void
    {
        $transport = new QueueBusTransportSpy();
        $bus = $this->bus($transport);
        $message = new QueueBusMessage();

        $bus->dispatch($message, transport: 'database', queue: 'critical', delaySeconds: 10);

        self::assertCount(1, $transport->enqueued);
        self::assertSame($message, $transport->enqueued[0]['message']->message());
        self::assertSame('critical', $transport->enqueued[0]['message']->queue());
        self::assertSame(10, $transport->enqueued[0]['delay']);
    }

    public function testSyncTransportAliasCannotSilentlyDiscardMessage(): void
    {
        $bus = new QueueBus(
            container: new Container(),
            transports: ['databse' => new SyncQueueTransport()],
            defaultTransport: 'databse',
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot enqueue messages');

        $bus->dispatch(new QueueBusMessage());
    }

    public function testProcessNextAcknowledgesAfterSuccessfulHandler(): void
    {
        $transport = new QueueBusTransportSpy(new QueuedMessage(new QueueBusMessage(), 'critical', 12, 3));
        $handled = 0;
        $bus = $this->bus($transport);
        $bus->addHandler(QueueBusMessage::class, static function () use (&$handled): void {
            $handled++;
        });

        self::assertTrue($bus->processNext('critical', 'database'));
        self::assertSame(1, $handled);
        self::assertCount(1, $transport->acknowledged);
        self::assertSame([], $transport->failed);
    }

    public function testProcessNextFailsAndRethrowsOriginalHandlerException(): void
    {
        $transport = new QueueBusTransportSpy(new QueuedMessage(new QueueBusMessage()));
        $exception = new \RuntimeException('handler failed');
        $bus = $this->bus($transport);
        $bus->addHandler(QueueBusMessage::class, static function () use ($exception): void {
            throw $exception;
        });

        try {
            $bus->processNext(transport: 'database');
            self::fail('Expected handler exception.');
        } catch (\RuntimeException $caught) {
            self::assertSame($exception, $caught);
        }

        self::assertSame([['error' => 'handler failed']], $transport->failed);
        self::assertSame([], $transport->acknowledged);
    }

    public function testFailExceptionPreservesOriginalHandlerException(): void
    {
        $transport = new QueueBusTransportSpy(new QueuedMessage(new QueueBusMessage()));
        $transport->failException = new \RuntimeException('fail transport failed');
        $handlerException = new \RuntimeException('handler failed');
        $bus = $this->bus($transport);
        $bus->addHandler(QueueBusMessage::class, static function () use ($handlerException): void {
            throw $handlerException;
        });

        try {
            $bus->processNext(transport: 'database');
            self::fail('Expected combined handler and fail exception.');
        } catch (QueueHandlerFailureException $caught) {
            self::assertSame($handlerException, $caught->getPrevious());
            self::assertSame($transport->failException, $caught->failException);
        }
    }

    public function testAckExceptionDoesNotInvokeFailFlow(): void
    {
        $transport = new QueueBusTransportSpy(new QueuedMessage(new QueueBusMessage()));
        $transport->ackException = new \RuntimeException('ack failed');
        $bus = $this->bus($transport);
        $bus->addHandler(QueueBusMessage::class, static function (): void {});

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ack failed');

        try {
            $bus->processNext(transport: 'database');
        } finally {
            self::assertSame([], $transport->failed);
        }
    }

    public function testLeaseLostInsideHandlerDoesNotInvokeFailFlow(): void
    {
        $transport = new QueueBusTransportSpy(new QueuedMessage(new QueueBusMessage()));
        $bus = $this->bus($transport);
        $bus->addHandler(QueueBusMessage::class, static function (): void {
            throw QueueLeaseLostException::forDelivery();
        });

        try {
            $bus->processNext(transport: 'database');
            self::fail('Expected lease loss.');
        } catch (QueueLeaseLostException) {
            self::assertSame([], $transport->failed);
            self::assertSame([], $transport->acknowledged);
        }
    }

    public function testRenewableTransportBindsLeaseOnlyForAsyncJobScope(): void
    {
        QueueBusLeaseAwareHandler::$renewedUntil = null;
        $transport = new QueueBusTransportSpy(new QueuedMessage(new QueueBusMessage()));
        $container = new Container();
        $bus = new QueueBus(
            container: $container,
            transports: [
                'database' => $transport,
            ],
        );
        $bus->addHandler(QueueBusMessage::class, QueueBusLeaseAwareHandler::class);

        self::assertTrue($bus->processNext(transport: 'database'));
        self::assertSame(123_456, QueueBusLeaseAwareHandler::$renewedUntil);
        self::assertCount(1, $transport->renewed);
    }

    public function testSynchronousJobScopeDoesNotExposeDatabaseLeaseCapability(): void
    {
        $bus = $this->bus();
        $bus->addHandler(QueueBusMessage::class, QueueBusLeaseAwareHandler::class);

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage(QueueLeaseInterface::class);

        $bus->dispatch(new QueueBusMessage(), transport: 'sync');
    }

    public function testJobContextSnapshotsQueuedMessageMetadata(): void
    {
        $message = new QueueBusMessage();
        $context = JobContext::fromQueuedMessage(new QueuedMessage($message, 'critical', 12, 3), 'database');

        self::assertSame($message, $context->message);
        self::assertSame('critical', $context->queue);
        self::assertSame(12, $context->jobId);
        self::assertSame(3, $context->attempt);
        self::assertSame('database', $context->transport);
    }

    public function testQueueProviderRegistersOperationalCommandDefinitions(): void
    {
        $container = new Container();
        (new ConsoleServiceProvider())->register($container);
        (new QueueServiceProvider())->register($container);
        $commands = $container->get(CommandRegistry::class);

        self::assertSame(QueueInstallCommand::class, $commands->definition('queue:install')->commandClass);
        self::assertSame(QueueWorkCommand::class, $commands->definition('queue:work')->commandClass);
        self::assertTrue($container->isBound(QueueInstallCommand::class));
        self::assertTrue($container->isBound(QueueWorkCommand::class));
    }

    public function testProviderKeepsSyncQueueUsableWithoutApplicationSecretOrCodecs(): void
    {
        $container = new Container();
        (new QueueServiceProvider())->register($container);
        $container->singleton(
            QueueConfig::class,
            new QueueConfig('sync', ['sync'], [], new QueueDatabaseConfig('jobs', 'failed_jobs')),
        );
        $bus = $container->get(QueueBusInterface::class);
        $handled = 0;
        $bus->addHandler(QueueBusMessage::class, static function () use (&$handled): void {
            $handled++;
        });

        $bus->dispatch(new QueueBusMessage());

        self::assertSame(1, $handled);
    }

    public function testProviderRejectsDatabaseQueueWithoutApplicationSecret(): void
    {
        $container = new Container();
        (new QueueServiceProvider())->register($container);
        $container->singleton(
            QueueConfig::class,
            new QueueConfig('database', ['database'], [], new QueueDatabaseConfig('jobs', 'failed_jobs')),
        );
        $container->singleton(
            AppConfig::class,
            new AppConfig(null, null, '', '', 'testing', false, '', '', '', null),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('APP_SECRET');

        $container->get(QueueBusInterface::class);
    }

    private function bus(?QueueBusTransportSpy $database = null): QueueBus
    {
        return new QueueBus(
            container: new Container(),
            transports: [
                'sync' => new QueueBusTransportSpy(),
                'database' => $database ?? new QueueBusTransportSpy(),
            ],
        );
    }
}

interface QueueBusMessageContract {}

class QueueBusParentMessage implements QueueBusMessageContract {}

final class QueueBusMessage extends QueueBusParentMessage {}

final class QueueBusInterfaceOnlyMessage implements QueueBusMessageContract {}

final class QueueBusClassStringHandler
{
    public static int $handled = 0;

    public function __invoke(QueueBusMessage $message): void
    {
        unset($message);
        self::$handled++;
    }
}

final class QueueBusLeaseAwareHandler
{
    public static ?int $renewedUntil = null;

    public function __construct(
        private readonly QueueLeaseInterface $lease,
    ) {
    }

    public function __invoke(QueueBusMessage $message): void
    {
        unset($message);
        self::$renewedUntil = $this->lease->renew();
    }
}

final class QueueBusTransportSpy implements RenewableQueueTransportInterface
{
    /**
     * @var list<array{message: QueuedMessage, delay: int}>
     */
    public array $enqueued = [];

    /** @var list<QueuedMessage> */
    public array $acknowledged = [];

    /** @var list<array{error:string}> */
    public array $failed = [];

    /** @var list<QueuedMessage> */
    public array $renewed = [];

    public ?\Throwable $ackException = null;
    public ?\Throwable $failException = null;

    public function __construct(
        private ?QueuedMessage $next = null,
    ) {}

    public function enqueue(QueuedMessage $message, int $delaySeconds = 0): void
    {
        $this->enqueued[] = [
            'message' => $message,
            'delay' => $delaySeconds,
        ];
    }

    public function dequeue(string $queue): ?QueuedMessage
    {
        unset($queue);
        $next = $this->next;
        $this->next = null;

        return $next;
    }

    public function ack(QueuedMessage $message): void
    {
        if ($this->ackException !== null) {
            throw $this->ackException;
        }

        $this->acknowledged[] = $message;
    }

    public function fail(QueuedMessage $message, string $error): void
    {
        if ($this->failException !== null) {
            throw $this->failException;
        }

        $this->failed[] = ['error' => $error];
    }

    public function renew(QueuedMessage $message): int
    {
        $this->renewed[] = $message;

        return 123_456;
    }
}
