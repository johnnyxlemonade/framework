<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Enum;

enum ColumnType: string
{
    case BigInteger = 'BIGINT';
    case Binary = 'BINARY';
    case Boolean = 'BOOLEAN';
    case Char = 'CHAR';
    case Date = 'DATE';
    case DateTime = 'DATETIME';
    case Decimal = 'DECIMAL';
    case Double = 'DOUBLE';
    case Float = 'FLOAT';
    case Integer = 'INT';
    case Json = 'JSON';
    case LongText = 'LONGTEXT';
    case MediumInteger = 'MEDIUMINT';
    case MediumText = 'MEDIUMTEXT';
    case SmallInteger = 'SMALLINT';
    case String = 'VARCHAR';
    case Text = 'TEXT';
    case Time = 'TIME';
    case Timestamp = 'TIMESTAMP';
    case TinyInteger = 'TINYINT';
    case TinyText = 'TINYTEXT';
    case Uuid = 'UUID';
}
