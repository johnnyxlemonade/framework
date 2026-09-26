<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

enum ServiceLifetime: string
{
    case Singleton = 'singleton';
    case Transient = 'transient';
    case Scoped = 'scoped';
}
