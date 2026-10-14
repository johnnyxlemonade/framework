<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Connection;

/**
 * Names SQL dialects whose grammar differs within supported connection drivers.
 */
enum DatabaseDialect: string
{
    case Mysql = 'mysql';
    case Odbc = 'odbc';
    case Sqlite = 'sqlite';
}
