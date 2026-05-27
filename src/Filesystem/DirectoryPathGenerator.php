<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem;

use InvalidArgumentException;

use function floor;
use function hash;
use function hash_algos;
use function in_array;
use function max;
use function min;
use function rtrim;
use function sprintf;
use function strlen;
use function substr;

final class DirectoryPathGenerator
{
    public function __construct(
        private readonly string $rootPath = '',
        private readonly string $algo = 'sha256',
    ) {
        if (!in_array($this->algo, hash_algos(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported hash algorithm "%s".',
                $this->algo,
            ));
        }
    }

    public function generate(
        int $module,
        int $type = 0,
        string|int|null $id = null,
        ?int $depth = null,
    ): string {
        $path = rtrim($this->rootPath, '/');

        if ($path !== '') {
            $path .= '/';
        }

        $path .= $module;

        if ($id === null) {
            return $path . '/';
        }

        $hash = hash($this->algo, (string) $id);
        $length = strlen($hash);

        $depth ??= max(4, min(8, (int) floor($length / 8)));

        if ($depth < 1 || $depth > 255) {
            throw new InvalidArgumentException('Depth must be between 1 and 255.');
        }

        $position = 0;
        $path .= '/' . $type;

        for ($i = 0; $i < $depth; $i++) {
            if ($position >= $length) {
                $position = 0;
            }

            $chunk = substr($hash, $position, 2);
            $position += 2;

            $path .= '/' . $chunk;
        }

        return rtrim($path, '/') . '/';
    }
}
