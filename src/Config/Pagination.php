<?php

declare(strict_types=1);

use Lemonade\Framework\Component\Pagination\Config\PaginationConfigDefinition;

return PaginationConfigDefinition::create()
    ->defaultPerPage(20)
    ->maxPerPage(100)
    ->visiblePages(7)
    ->showFirstLast(true)
    ->classes([]);
