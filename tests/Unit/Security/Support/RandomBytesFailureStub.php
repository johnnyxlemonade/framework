<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security;

use Random\RandomException;

/**
 * Replaces the namespaced entropy source in an isolated test process to exercise fail-closed behavior.
 *
 * @throws RandomException Always, so production code cannot receive predictable fallback bytes.
 */
function random_bytes(int $length): string
{
    throw new RandomException(sprintf('random_bytes failed for %d bytes.', $length));
}
