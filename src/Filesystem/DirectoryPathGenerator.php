<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem;

use InvalidArgumentException;

use function hash;
use function implode;
use function strlen;
use function substr;

/**
 * Derives deterministic bounded-fan-out directory paths from identifiers.
 *
 * It does not create directories or own storage-root policy.
 */
final readonly class DirectoryPathGenerator
{
    /**
     * Maps an arbitrary key into deterministic two-hex-character directory buckets.
     *
     * It calculates a path only; callers retain ownership of directory creation and storage policy.
     */
    public function shard(string|int $key, ?int $depth = null): string
    {
        $hash = hash('sha256', (string) $key);
        $length = strlen($hash);
        $depth ??= 8;
        if ($depth < 1 || $depth > intdiv($length, 2)) {
            throw new InvalidArgumentException('Depth must be between 1 and 32 for SHA-256 sharding.');
        }
        $segments = [];
        for ($position = 0, $i = 0; $i < $depth; $i++, $position += 2) {
            $segments[] = substr($hash, $position, 2);
        }
        return implode('/', $segments) . '/';
    }
}
