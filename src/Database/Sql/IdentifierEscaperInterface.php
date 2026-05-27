<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Sql;

interface IdentifierEscaperInterface
{
    public function identifier(string $identifier): string;

    public function table(string $table): string;
}
