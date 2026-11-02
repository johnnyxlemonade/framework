<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Instrumentation;

use Lemonade\Framework\Database\Connection\DatabaseDialect;

/**
 * Receives individual database timings and original bindings, without results or exception details.
 *
 * SQL and binding values are unredacted: implementations must sanitize them before retaining or displaying anything.
 * An observer failure disables notifications for the remaining capture without affecting SQL totals.
 */
interface DatabaseObserverInterface
{
    /**
     * Receives one prepare/bind/execute attempt, excluding connection open and subsequent fetching.
     *
     * Successful means the operation returned normally; failed attempts are included in SQL totals.
     * @param array<int|string, mixed> $bindings
     */
    public function queryCompleted(string $sql, float $durationMs, bool $successful, array $bindings = [], ?DatabaseDialect $dialect = null): void;

    /**
     * Receives a connection-open/session-setup attempt separately from query counts and SQL time.
     */
    public function connectionCompleted(float $durationMs, bool $successful): void;
}
