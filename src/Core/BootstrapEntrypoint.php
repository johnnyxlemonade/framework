<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

/**
 * Internal application bootstrap entrypoints.
 *
 * @internal
 */
enum BootstrapEntrypoint: string
{
    case Http = 'http';
    case Cli = 'cli';
}
