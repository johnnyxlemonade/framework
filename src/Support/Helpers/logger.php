<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

if (!function_exists('logger')) {
    function logger(): LoggerInterface
    {
        return new NullLogger();
    }
}
