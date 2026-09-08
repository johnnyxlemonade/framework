<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Migration;

/**
 * State of a registered migration.
 */
enum MigrationState: string
{
    /** The migration is recorded in the database history. */
    case Applied = 'applied';

    /** The migration is registered but absent from the database history. */
    case Pending = 'pending';

    /** The database history could not be verified; this is not a migration failure. */
    case Unknown = 'unknown';
}
