<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Enum;

/**
 * Provides the foreign key action contract for the database subsystem.
 */
enum ForeignKeyAction: string
{
    case Cascade = 'CASCADE';
    case Restrict = 'RESTRICT';
    case NoAction = 'NO ACTION';
    case SetNull = 'SET NULL';
    case SetDefault = 'SET DEFAULT';
}
