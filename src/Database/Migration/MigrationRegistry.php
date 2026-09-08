<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Migration;

use DateTimeImmutable;
use InvalidArgumentException;
use Lemonade\Framework\Container\ContainerInterface;
use LogicException;

/**
 * Registry of explicitly registered migration class strings.
 *
 * Registration validates migration identities and establishes deterministic
 * ordering without instantiating migrations.
 */
final class MigrationRegistry
{
    /** @var array<string, class-string<MigrationInterface>> */
    private array $migrations = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * Registers a migration class without instantiating it.
     *
     * @param class-string<MigrationInterface> $migrationClass
     *
     * @throws InvalidArgumentException When the class or its identifier is invalid.
     * @throws LogicException When the identifier is already registered.
     */
    public function register(string $migrationClass): void
    {
        if (!class_exists($migrationClass)) {
            throw new InvalidArgumentException(sprintf('Migration class "%s" does not exist.', $migrationClass));
        }

        if (!is_subclass_of($migrationClass, MigrationInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Migration class "%s" must implement %s.',
                $migrationClass,
                MigrationInterface::class,
            ));
        }

        $identifier = self::validateIdentifier($migrationClass::identifier());

        if (isset($this->migrations[$identifier])) {
            throw new LogicException(sprintf('Migration identifier "%s" is already registered.', $identifier));
        }

        $this->migrations[$identifier] = $migrationClass;
    }

    /**
     * Returns ordered migration identifiers without resolving migration instances.
     *
     * @return list<string>
     */
    public function identifiers(): array
    {
        $identifiers = array_keys($this->migrations);
        sort($identifiers, SORT_STRING);

        return $identifiers;
    }

    /**
     * Resolves a registered migration only when it is needed for execution.
     *
     * @throws LogicException When the identifier is not registered or resolution is invalid.
     */
    public function get(string $identifier): MigrationInterface
    {
        $migrationClass = $this->migrations[$identifier] ?? null;
        if ($migrationClass === null) {
            throw new LogicException(sprintf('Migration "%s" is not registered.', $identifier));
        }

        $migration = $this->container->get($migrationClass);
        if (!$migration instanceof MigrationInterface) {
            throw new LogicException(sprintf(
                'Migration class "%s" did not resolve to %s.',
                $migrationClass,
                MigrationInterface::class,
            ));
        }

        return $migration;
    }

    private static function validateIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            throw new InvalidArgumentException('Migration identifier cannot be empty.');
        }

        if (preg_match('/^(\d{14})_([a-z0-9_]+)$/', $identifier, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Migration identifier "%s" must match YYYYMMDDHHMMSS_description.',
                $identifier,
            ));
        }

        $timestamp = DateTimeImmutable::createFromFormat('!YmdHis', $matches[1]);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            !$timestamp instanceof DateTimeImmutable
            || $timestamp->format('YmdHis') !== $matches[1]
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            throw new InvalidArgumentException(sprintf(
                'Migration identifier "%s" contains an invalid timestamp.',
                $identifier,
            ));
        }

        return $identifier;
    }
}
