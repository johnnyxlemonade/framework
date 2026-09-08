<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Migration;

use DateTimeImmutable;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\QueryBuilder;
use Lemonade\Framework\Database\Schema\Schema;
use RuntimeException;

/**
 * Manages the persistent history of applied migrations.
 *
 * The repository uses the same DatabaseDriverInterface instance as Schema so state
 * reads and writes target the active schema connection.
 */
final class MigrationStateRepository
{
    private const TABLE = 'migrations';

    public function __construct(
        private readonly DatabaseDriverInterface $driver,
        private readonly Schema $schema,
    ) {}

    /**
     * Creates the migrations state table when it does not already exist.
     */
    public function ensureTable(): void
    {
        $created = $this->schema->create(self::TABLE, static function ($table): void {
            $table->string('migration', 191)->primary();
            $table->datetime('applied_at');
        }, true);

        if (!$created) {
            throw new RuntimeException('Unable to create the migrations state table.');
        }
    }

    /**
     * Returns identifiers stored in the persistent migration history.
     *
     * @return list<string>
     */
    public function applied(): array
    {
        $rows = QueryBuilder::make($this->driver)
            ->table(self::TABLE)
            ->select(['migration'])
            ->orderBy('migration')
            ->getArray();

        $identifiers = [];
        foreach ($rows as $row) {
            $identifier = $row['migration'] ?? null;
            if (is_string($identifier)) {
                $identifiers[] = $identifier;
            }
        }

        return $identifiers;
    }

    /**
     * Records an identifier after its migration has completed successfully.
     */
    public function record(string $identifier): void
    {
        $stored = QueryBuilder::make($this->driver)
            ->table(self::TABLE)
            ->set([
                'migration' => $identifier,
                'applied_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ])
            ->insert();

        if (!$stored) {
            throw new RuntimeException(sprintf('Unable to record migration "%s".', $identifier));
        }
    }
}
