<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Queue;

use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Cli\ConsoleServiceProvider;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Queue\Cli\QueueInstallCommand;
use Lemonade\Framework\Queue\Cli\QueueWorkCommand;
use Lemonade\Framework\Queue\Exception\QueueHandlerFailureException;
use Lemonade\Framework\Queue\JobContext;
use Lemonade\Framework\Queue\JobHandlerRegistry;
use Lemonade\Framework\Queue\QueuedMessage;
use Lemonade\Framework\Queue\QueueBus;
use Lemonade\Framework\Queue\QueueServiceProvider;
use Lemonade\Framework\Queue\QueueTransportInterface;
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

final class QueueBusTransportSpy implements QueueTransportInterface
{
    /** @var list<QueuedMessage> */
    public array $acknowledged = [];

    /** @var list<array{error:string}> */
    public array $failed = [];

    public ?\Throwable $ackException = null;
    public ?\Throwable $failException = null;

    public function __construct(
        private ?QueuedMessage $next = null,
    ) {}

    public function enqueue(QueuedMessage $message, int $delaySeconds = 0): void
    {
        unset($message, $delaySeconds);
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
}
