<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Concerns;

use Throwable;

/**
 * Shares nested transaction bookkeeping while issuing physical boundaries only for the outermost scope.
 */
trait ManagesTransactions
{
    private bool $transEnabled = true;

    private bool $transStrict = true;

    private int $transDepth = 0;

    private bool $transStatus = true;

    private bool $transFailure = false;

    /**
     * Disables transaction entry points for the consuming connection.
     */
    public function trans_off(): void
    {
        $this->transEnabled = false;
    }

    /**
     * Controls whether a failed transaction keeps the connection in failed status for later transaction groups.
     */
    public function trans_strict(bool $mode = true): void
    {
        $this->transStrict = $mode;
    }

    /**
     * Starts a managed transaction group, optionally forcing rollback during completion for test mode.
     */
    public function trans_start(bool $testMode = false): bool
    {
        if (!$this->transEnabled) {
            return false;
        }

        return $this->trans_begin($testMode);
    }

    /**
     * Commits a successful group or rolls it back after a recorded failure.
     */
    public function trans_complete(): bool
    {
        if (!$this->transEnabled) {
            return false;
        }

        if ($this->transStatus === false || $this->transFailure === true) {
            $this->trans_rollback();

            if ($this->transStrict === false) {
                $this->transStatus = true;
            }

            return false;
        }

        return $this->trans_commit();
    }

    /**
     * Reports whether the current transaction group has remained successful.
     */
    public function trans_status(): bool
    {
        return $this->transStatus;
    }

    /**
     * Reports whether this connection has one or more managed transaction scopes open.
     */
    public function trans_active(): bool
    {
        return $this->transDepth > 0;
    }

    /**
     * Enters a transaction scope, issuing a physical BEGIN only for the outermost scope.
     */
    public function trans_begin(bool $testMode = false): bool
    {
        if (!$this->transEnabled) {
            return false;
        }

        if ($this->transDepth > 0) {
            $this->transDepth++;

            return true;
        }

        $this->transFailure = $testMode;

        try {
            $this->beginTransaction();
            $this->transStatus = true;
            $this->transDepth++;

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Leaves a transaction scope, issuing a physical commit only when the outermost scope completes.
     */
    public function trans_commit(): bool
    {
        if (!$this->transEnabled || $this->transDepth === 0) {
            return false;
        }

        if ($this->transDepth > 1) {
            $this->transDepth--;

            return true;
        }

        try {
            $this->commitTransaction();
            $this->transDepth = 0;

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Leaves a transaction scope, issuing a physical rollback only when the outermost scope completes.
     */
    public function trans_rollback(): bool
    {
        if (!$this->transEnabled || $this->transDepth === 0) {
            return false;
        }

        if ($this->transDepth > 1) {
            $this->transDepth--;

            return true;
        }

        try {
            $this->rollbackTransaction();
            $this->transDepth = 0;

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Marks the current transaction group failed so completion rolls it back.
     */
    protected function markTransactionFailure(): void
    {
        $this->transStatus = false;
    }

    /**
     * Starts the driver's physical transaction boundary for the outermost managed scope.
     */
    abstract protected function beginTransaction(): void;

    /**
     * Commits the driver's physical transaction boundary for the outermost managed scope.
     */
    abstract protected function commitTransaction(): void;

    /**
     * Rolls back the driver's physical transaction boundary for the outermost managed scope.
     */
    abstract protected function rollbackTransaction(): void;
}
