<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Queue;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\Exception\ScopedContainerClosedException;
use Lemonade\Framework\Container\ScopeKind;
use Lemonade\Framework\Container\ScopedContainerInterface;
use Lemonade\Framework\Queue\JobContext;
use Lemonade\Framework\Queue\JobHandlerInvoker;
use Lemonade\Framework\Queue\JobHandlerRegistry;
use Lemonade\Framework\Queue\QueuedMessage;
use Lemonade\Framework\Queue\QueueBus;
use Lemonade\Framework\Queue\QueueTransportInterface;
use PHPUnit\Framework\TestCase;

final class JobHandlerInvokerTest extends TestCase
{
    protected function setUp(): void
    {
        InvokerScopedHandler::reset();
        InvokerQueuedMessageHandler::$queuedMessage = null;
        InvokerScopeRecordingHandler::$scope = null;
        InvokerFailingScopeHandler::$scope = null;
        InvokerRequestIsolationHandler::$scope = null;
        InvokerRequestIsolationHandler::$sawRequestLocal = false;
    }

    public function testClassStringHandlerResolvesFromJobScopeWithJobContext(): void
    {
        $container = $this->containerWithScopedHandler();
        $registry = new JobHandlerRegistry();
        $registry->register(InvokerMessage::class, InvokerScopedHandler::class);
        $invoker = new JobHandlerInvoker($registry, $container);

        $invoker->invoke($this->context(new InvokerMessage(), queue: 'critical'));

        self::assertSame(1, InvokerScopedHandler::$instances);
        self::assertSame('critical', InvokerScopedHandler::$contexts[0]->queue);
    }

    public function testAsyncInvocationBindsQueuedMessageIntoJobScope(): void
    {
        $container = new Container();
        $container->scoped(InvokerQueuedMessageHandler::class, InvokerQueuedMessageHandler::class);
        $registry = new JobHandlerRegistry();
        $registry->register(InvokerMessage::class, InvokerQueuedMessageHandler::class);
        $invoker = new JobHandlerInvoker($registry, $container);
        $item = new QueuedMessage(new InvokerMessage(), 'critical', 12, 3);

        $invoker->invoke(JobContext::fromQueuedMessage($item, 'database'), $item);

        self::assertSame($item, InvokerQueuedMessageHandler::$queuedMessage);
    }

    public function testSeparateJobsDoNotShareScopedHandlerOrDependency(): void
    {
        $container = $this->containerWithScopedHandler();
        $registry = new JobHandlerRegistry();
        $registry->register(InvokerMessage::class, InvokerScopedHandler::class);
        $invoker = new JobHandlerInvoker($registry, $container);

        $invoker->invoke($this->context(new InvokerMessage()));
        $invoker->invoke($this->context(new InvokerMessage()));

        self::assertCount(2, InvokerScopedHandler::$handlers);
        self::assertNotSame(InvokerScopedHandler::$handlers[0], InvokerScopedHandler::$handlers[1]);
        self::assertNotSame(InvokerScopedHandler::$dependencies[0], InvokerScopedHandler::$dependencies[1]);
    }

    public function testJobScopeClosesAfterSuccess(): void
    {
        $container = new Container();
        $container->scoped(InvokerScopeRecordingHandler::class, InvokerScopeRecordingHandler::class);
        $registry = new JobHandlerRegistry();
        $registry->register(InvokerMessage::class, InvokerScopeRecordingHandler::class);

        (new JobHandlerInvoker($registry, $container))->invoke($this->context(new InvokerMessage()));

        $this->assertScopeClosed(InvokerScopeRecordingHandler::$scope);
    }

    public function testJobScopeClosesAfterHandlerException(): void
    {
        $container = new Container();
        $container->scoped(InvokerFailingScopeHandler::class, InvokerFailingScopeHandler::class);
        $registry = new JobHandlerRegistry();
        $registry->register(InvokerMessage::class, InvokerFailingScopeHandler::class);

        try {
            (new JobHandlerInvoker($registry, $container))->invoke($this->context(new InvokerMessage()));
            self::fail('Expected handler exception.');
        } catch (\RuntimeException $exception) {
            self::assertSame('handler failed', $exception->getMessage());
        }

        $this->assertScopeClosed(InvokerFailingScopeHandler::$scope);
    }

    public function testSyncDispatchUsesJobScopeAndDoesNotReuseRequestScope(): void
    {
        $container = new Container();
        $requestScope = $container->beginScope(ScopeKind::Request);
        $requestScope->bindScopedInstance('request.local.value', new InvokerRequestLocalValue());
        $container->scoped(InvokerRequestIsolationHandler::class, InvokerRequestIsolationHandler::class);
        $registry = new JobHandlerRegistry();
        $registry->register(InvokerMessage::class, InvokerRequestIsolationHandler::class);
        $invoker = new JobHandlerInvoker($registry, $container);
        $bus = new QueueBus(
            container: $container,
            transports: ['sync' => new InvokerTransportSpy()],
            handlers: $registry,
            invoker: $invoker,
        );

        $bus->dispatch(new InvokerMessage(), transport: 'sync');

        self::assertNotSame($requestScope, InvokerRequestIsolationHandler::$scope);
        self::assertFalse(InvokerRequestIsolationHandler::$sawRequestLocal);
        $requestScope->close();
    }

    public function testCallableHandlerRemainsCompatible(): void
    {
        $container = new Container();
        $registry = new JobHandlerRegistry();
        $handled = [];
        $registry->register(InvokerMessage::class, static function (object $message) use (&$handled): void {
            $handled[] = $message;
        });

        $message = new InvokerMessage();
        (new JobHandlerInvoker($registry, $container))->invoke($this->context($message));

        self::assertSame([$message], $handled);
    }

    private function containerWithScopedHandler(): Container
    {
        $container = new Container();
        $container->scoped(InvokerScopedDependency::class, InvokerScopedDependency::class);
        $container->scoped(InvokerScopedHandler::class, InvokerScopedHandler::class);

        return $container;
    }

    private function context(object $message, string $queue = 'default'): JobContext
    {
        return new JobContext($message, $queue, null, 0, 'sync');
    }

    private function assertScopeClosed(?ScopedContainerInterface $scope): void
    {
        self::assertInstanceOf(ScopedContainerInterface::class, $scope);
        $this->expectException(ScopedContainerClosedException::class);
        $scope->get(JobContext::class);
    }
}

final class InvokerMessage {}

final class InvokerScopedDependency {}

final class InvokerScopedHandler
{
    /** @var list<InvokerScopedHandler> */
    public static array $handlers = [];

    /** @var list<InvokerScopedDependency> */
    public static array $dependencies = [];

    /** @var list<JobContext> */
    public static array $contexts = [];

    public static int $instances = 0;

    public function __construct(
        JobContext $context,
        InvokerScopedDependency $dependency,
    ) {
        self::$instances++;
        self::$handlers[] = $this;
        self::$dependencies[] = $dependency;
        self::$contexts[] = $context;
    }

    public static function reset(): void
    {
        self::$handlers = [];
        self::$dependencies = [];
        self::$contexts = [];
        self::$instances = 0;
    }

    public function __invoke(InvokerMessage $message): void
    {
        unset($message);
    }
}

final class InvokerQueuedMessageHandler
{
    public static ?QueuedMessage $queuedMessage = null;

    public function __construct(QueuedMessage $queuedMessage)
    {
        self::$queuedMessage = $queuedMessage;
    }

    public function __invoke(InvokerMessage $message): void
    {
        unset($message);
    }
}

final class InvokerScopeRecordingHandler
{
    public static ?ScopedContainerInterface $scope = null;

    public function __construct(ScopedContainerInterface $scope)
    {
        self::$scope = $scope;
    }

    public function __invoke(InvokerMessage $message): void
    {
        unset($message);
    }
}

final class InvokerFailingScopeHandler
{
    public static ?ScopedContainerInterface $scope = null;

    public function __construct(ScopedContainerInterface $scope)
    {
        self::$scope = $scope;
    }

    public function __invoke(InvokerMessage $message): void
    {
        unset($message);
        throw new \RuntimeException('handler failed');
    }
}

final class InvokerRequestLocalValue {}

final class InvokerRequestIsolationHandler
{
    public static ?ScopedContainerInterface $scope = null;
    public static bool $sawRequestLocal = false;

    public function __construct(ScopedContainerInterface $scope)
    {
        self::$scope = $scope;
        self::$sawRequestLocal = $scope->has('request.local.value');
    }

    public function __invoke(InvokerMessage $message): void
    {
        unset($message);
    }
}

final class InvokerTransportSpy implements QueueTransportInterface
{
    public function enqueue(QueuedMessage $message, int $delaySeconds = 0): void
    {
        unset($message, $delaySeconds);
    }

    public function dequeue(string $queue): ?QueuedMessage
    {
        unset($queue);

        return null;
    }

    public function ack(QueuedMessage $message): void
    {
        unset($message);
    }

    public function fail(QueuedMessage $message, string $error): void
    {
        unset($message, $error);
    }
}
