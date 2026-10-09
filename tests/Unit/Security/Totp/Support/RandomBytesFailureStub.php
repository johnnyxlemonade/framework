<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Totp;

use RuntimeException;

function random_bytes(int $length): string
{
    throw new RuntimeException(sprintf('random_bytes failed for %d bytes.', $length));
}
