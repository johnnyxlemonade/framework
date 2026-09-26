<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

use Lemonade\Framework\Container\ScopedContainerInterface;
use Lemonade\Framework\Container\ScopeFactoryInterface;
use Lemonade\Framework\Container\ScopeKind;

final class JobHandlerInvoker
{
    public function __construct(
        private readonly JobHandlerRegistry $handlers,
        private readonly ScopeFactoryInterface $scopeFactory,
    ) {}

    public function invoke(JobContext $context, ?QueuedMessage $queuedMessage = null): void
    {
        $scope = $this->scopeFactory->beginScope(ScopeKind::Job);
        $scope->bindScopedInstance(JobContext::class, $context);

        if ($queuedMessage !== null) {
            $scope->bindScopedInstance(QueuedMessage::class, $queuedMessage);
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
