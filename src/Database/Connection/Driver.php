<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Connection;

enum Driver: string
{
    case Pdo = 'pdo';
    case Mysql = 'mysql';
    case Odbc = 'odbc';
}
