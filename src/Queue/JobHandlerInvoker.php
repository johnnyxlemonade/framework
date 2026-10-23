<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

use Lemonade\Framework\Container\ScopedContainerInterface;
use Lemonade\Framework\Container\ScopeFactoryInterface;
use Lemonade\Framework\Container\ScopeKind;

/**
 * Resolves one registered handler inside an isolated Job scope and closes that scope after invocation.
 */
final class JobHandlerInvoker
{
    /**
     * Creates an invoker over the handler registry and the container's scope factory.
     */
    public function __construct(
        private readonly JobHandlerRegistry $handlers,
        private readonly ScopeFactoryInterface $scopeFactory,
    ) {
    }

    /**
     * Binds delivery context and optional asynchronous capabilities before invoking the selected handler.
     */
    public function invoke(
        JobContext $context,
        ?QueuedMessage $queuedMessage = null,
        ?QueueLeaseInterface $queueLease = null,
    ): void {
        $scope = $this->scopeFactory->beginScope(ScopeKind::Job);
        $scope->bindScopedInstance(JobContext::class, $context);

        if ($queuedMessage !== null) {
            $scope->bindScopedInstance(QueuedMessage::class, $queuedMessage);
        }

        if ($queueLease !== null) {
            $scope->bindScopedInstance(QueueLeaseInterface::class, $queueLease);
        }

        try {
            $handler = $this->handlers->handlerFor($context->message);
            $resolved = $this->resolveCallable($scope, $handler);
            $resolved($context->message);
        } finally {
            $scope->close();
        }
    }

    /**
     * @param (callable(object): void)|string $handler
     * @return callable(object):void
     */
    private function resolveCallable(ScopedContainerInterface $scope, callable|string $handler): callable
    {
        if (is_callable($handler)) {
            return static function (object $message) use ($handler): void {
                $handler($message);
            };
        }

        $resolved = $scope->get($handler);

        if (is_callable($resolved)) {
            return static function (object $message) use ($resolved): void {
                $resolved($message);
            };
        }

        if (is_object($resolved) && method_exists($resolved, '__invoke')) {
            return static function (object $message) use ($resolved): void {
                $resolved($message);
            };
        }

        throw new \RuntimeException(sprintf('Queue handler "%s" is not callable.', $handler));
    }
}
