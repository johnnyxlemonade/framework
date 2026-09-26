<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

use Lemonade\Framework\Core\ServiceProviderInterface;

final readonly class FrameworkConfig
{
    /**
     * @param list<class-string<ServiceProviderInterface>> $providers
     */
    public function __construct(
        public array $providers,
    ) {}
}
