<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Model;

final class DumpType
{
    public const NULL = 'null';
    public const BOOL = 'bool';
    public const INT = 'int';
    public const FLOAT = 'float';
    public const STRING = 'string';
    public const ARRAY = 'array';
    public const OBJECT = 'object';
    public const ENTRY = 'entry';
    public const PROPERTY = 'property';
    public const RESOURCE = 'resource';
    public const DEPTH_LIMIT = 'depth_limit';
    public const UNKNOWN = 'unknown';

    private function __construct() {}
}
