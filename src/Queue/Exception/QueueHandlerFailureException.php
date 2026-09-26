<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Exception;

use RuntimeException;
use Throwable;

final class QueueHandlerFailureException extends RuntimeException
{
    public function __construct(
        Throwable $handlerException,
        public readonly Throwable $failException,
    ) {
        parent::__construct(
            'Queue handler failed and the transport fail operation also failed: ' . $failException->getMessage(),
            previous: $handlerException,
        );
    }
}
