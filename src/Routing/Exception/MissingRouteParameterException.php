<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing\Exception;

use InvalidArgumentException;

final class MissingRouteParameterException extends InvalidArgumentException
{
    public function __construct(
        private readonly string $parameter,
    ) {
        parent::__construct(sprintf(
            'Missing route parameter "%s".',
            $parameter,
        ));
    }

    public function parameter(): string
    {
        return $this->parameter;
    }
}
