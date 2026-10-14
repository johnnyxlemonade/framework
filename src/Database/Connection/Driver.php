<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Connection;

/**
 * Identifies the connection and driver families supported by the database subsystem.
 */
enum Driver: string
{
    case Pdo = 'pdo';
    case Mysql = 'mysql';
    case Odbc = 'odbc';
}
