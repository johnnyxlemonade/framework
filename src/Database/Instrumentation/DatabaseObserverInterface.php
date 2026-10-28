<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Instrumentation;

/**
 * Receives individual database timings without bindings, results or exception details.
 *
 * SQL is unredacted: implementations must sanitize literals before retaining or displaying it.
 * An observer failure disables notifications for the remaining capture without affecting SQL totals.
 */
interface DatabaseObserverInterface
{
    /**
     * Receives one prepare/bind/execute attempt, excluding connection open and subsequent fetching.
     *
     * Successful means the operation returned normally; failed attempts are included in SQL totals.
     */
    public function queryCompleted(string $sql, float $durationMs, bool $successful): void;

    /**
     * Receives a connection-open/session-setup attempt separately from query counts and SQL time.
     */
    public function connectionCompleted(float $durationMs, bool $successful): void;
}
