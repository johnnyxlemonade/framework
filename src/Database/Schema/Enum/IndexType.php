<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Enum;

enum IndexType: string
{
    case Primary = 'PRIMARY';
    case Index = 'INDEX';
    case Unique = 'UNIQUE';
    case Fulltext = 'FULLTEXT';
    case Spatial = 'SPATIAL';
}
