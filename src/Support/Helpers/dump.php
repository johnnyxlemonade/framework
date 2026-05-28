<?php

declare(strict_types=1);

use Lemonade\Framework\Debug\Dump\Contract\DumperInterface;
use Lemonade\Framework\Debug\Dump\DefaultDumperFactory;

if (!function_exists('dumper')) {
    function dumper(): DumperInterface
    {
        $dumper = service(DumperInterface::class);

        if ($dumper instanceof DumperInterface) {
            return $dumper;
        }

        return DefaultDumperFactory::create();
    }
}

if (!function_exists('dump')) {
    function dump(mixed ...$values): void
    {
        dumper()->dump(...$values);
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$values): never
    {
        dumper()->dd(...$values);
    }
}
