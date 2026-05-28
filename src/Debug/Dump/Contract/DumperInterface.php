<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Contract;

interface DumperInterface
{
    public function dump(mixed ...$values): void;

    public function render(mixed ...$values): string;

    /**
     */
    public function dd(mixed ...$values): never;
}
