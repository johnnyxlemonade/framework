<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container\Config;

/**
 * Defines whether an unbound concrete class may be constructed by reflection.
 */
enum AutowireMode: string
{
    case Permissive = 'permissive';
    case Strict = 'strict';
}
