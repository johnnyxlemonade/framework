<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

enum ScopeKind: string
{
    case Request = 'request';
    case Command = 'command';
    case Job = 'job';
}
