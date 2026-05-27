<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Connection;

enum DatabaseDialect: string
{
    case Mysql = 'mysql';
    case Odbc = 'odbc';
}
