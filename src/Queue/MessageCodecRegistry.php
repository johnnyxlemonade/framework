<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

use InvalidArgumentException;
use Lemonade\Framework\Queue\Exception\InvalidQueuePayloadException;
use LogicException;

/**
 * Maps stable queue aliases to explicitly trusted message classes and codecs.
 *
 * Handler registration never changes this allow-list; applications extend it deliberately from a
 * service provider before resolving the database queue transport.
 */
final class MessageCodecRegistry
{
    /**
     * @var array<non-empty-string, array{
     *     class: class-string,
     *     codec: MessageCodecInterface,
     *     version: positive-int
     * }>
     */
    private array $byAlias = [];

    /** @var array<class-string, non-empty-string> */
    private array $aliasByClass = [];

    /**
     * Registers one stable alias and schema version for an exact message class.
     *
     * Reusing an alias or class is rejected so provider order cannot silently change persisted
     * message identity.
     *
     * @param non-empty-string $alias
     * @param class-string $messageClass
     * @param positive-int $version
     */
    public function register(
        string $alias,
        string $messageClass,
        MessageCodecInterface $codec,
        int $version = 1,
    ): void {
        if (preg_match('/^[a-z][a-z0-9._-]{0,119}$/D', $alias) !== 1) {
            throw new InvalidArgumentException(
                'Queue message aliases must start with a lowercase letter and contain only lowercase letters, digits, dots, underscores, or hyphens.',
            );
        }

        if (!class_exists($messageClass)) {
            throw new InvalidArgumentException(sprintf(
                'Queue message class "%s" does not exist.',
                $messageClass,
            ));
        }

        if ($version < 1) {
            throw new InvalidArgumentException('Queue message codec versions must be positive integers.');
        }

        if (isset($this->byAlias[$alias])) {
            throw new LogicException(sprintf('Queue message alias "%s" is already registered.', $alias));
        }

        if (isset($this->aliasByClass[$messageClass])) {
            throw new LogicException(sprintf('Queue message class "%s" is already registered.', $messageClass));
        }

        $this->byAlias[$alias] = [
            'class' => $messageClass,
            'codec' => $codec,
            'version' => $version,
        ];
        $this->aliasByClass[$messageClass] = $alias;
    }

    /**
     * Encodes an exact registered message class and returns its persisted type identity.
     *
     * @return array{
     *     type: non-empty-string,
     *     version: positive-int,
     *     payload: array<int|string, mixed>
     * }
     */
    public function encode(object $message): array
    {
        $messageClass = $message::class;
        $alias = $this->aliasByClass[$messageClass] ?? null;

        if ($alias === null) {
            throw new LogicException(sprintf(
                'Queue message class "%s" has no registered codec.',
                $messageClass,
            ));
        }

        $registration = $this->byAlias[$alias];

        return [
            'type' => $alias,
            'version' => $registration['version'],
            'payload' => $registration['codec']->encode($message),
        ];
    }

    /**
     * Reconstructs a message only when its authenticated alias and version are registered.
     *
     * @param array<int|string, mixed> $payload
     *
     * @throws InvalidQueuePayloadException When the alias, schema version, or codec result is invalid.
     */
    public function decode(string $alias, int $version, array $payload): object
    {
        $registration = $this->byAlias[$alias] ?? null;

        if ($registration === null) {
            throw InvalidQueuePayloadException::unknownType();
        }

        if ($registration['version'] !== $version) {
            throw InvalidQueuePayloadException::unsupportedVersion();
        }

        try {
            $message = $registration['codec']->decode($payload);
        } catch (\Throwable $exception) {
            throw InvalidQueuePayloadException::codecFailure($exception);
        }

        if (!$message instanceof $registration['class']) {
            throw InvalidQueuePayloadException::codecTypeMismatch();
        }

        return $message;
    }
}
