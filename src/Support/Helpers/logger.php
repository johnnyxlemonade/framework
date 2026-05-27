<?php

declare(strict_types=1);

use Lemonade\Framework\Core\Logging\LogManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

if (!function_exists('logger')) {
    function logger(?string $channel = null): LoggerInterface
    {
        $manager = service(LogManager::class);

        if (!$manager instanceof LogManager) {
            return new NullLogger();
        }

        return match ($channel) {
            null, '', 'benchmark' => $manager->benchmark(),
            'request' => $manager->request(),
            'error' => $manager->error(),
            default => $manager->error(),
        };
    }
}
