<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Migration;

use Lemonade\Framework\Database\Schema\Schema;

/**
 * Contract for a one-way database migration.
 *
 * The identifier gives a migration its stable identity and determines its
 * execution order. The migration applies its schema changes through the
 * existing Schema API.
 */
interface MigrationInterface
{
    /**
     * Returns a stable YYYYMMDDHHMMSS_description identifier.
     *
     * An identifier must not change after the migration has been applied.
     */
    public static function identifier(): string;

    /**
     * Applies the schema change.
     *
     * The framework does not provide automatic rollback or guarantee DDL atomicity.
     */
    public function up(Schema $schema): void;
}
