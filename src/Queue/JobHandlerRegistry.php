<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

final class JobHandlerRegistry
{
    /**
     * @var array<string, (callable(object): void)|string>
     */
    private array $handlers = [];

    /**
     * The latest registration for a message class replaces the previous one,
     * preserving QueueBus::addHandler() compatibility.
     *
     * @param (callable(object): void)|string $handler
     */
    public function register(string $messageClass, callable|string $handler): void
    {
        $this->handlers[$messageClass] = $handler;
    }

    /**
     * @return (callable(object): void)|string
     */
    public function handlerFor(object $message): callable|string
    {
        $class = $message::class;

        if (isset($this->handlers[$class])) {
            return $this->handlers[$class];
        }

        foreach (class_parents($message) as $parent) {
            if (isset($this->handlers[$parent])) {
                return $this->handlers[$parent];
            }
        }

        foreach (class_implements($message) as $interface) {
            if (isset($this->handlers[$interface])) {
                return $this->handlers[$interface];
            }
        }

        throw new \RuntimeException(sprintf('Queue handler for "%s" is not registered.', $class));
    }
}
