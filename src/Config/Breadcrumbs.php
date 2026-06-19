<?php

declare(strict_types=1);

use Lemonade\Framework\Component\Breadcrumb\Config\BreadcrumbsConfigDefinition;

return BreadcrumbsConfigDefinition::create()
    ->frontendRoot('Domu', '/')
    ->adminRoot('Admin', '/admin')
    ->classes([]);
