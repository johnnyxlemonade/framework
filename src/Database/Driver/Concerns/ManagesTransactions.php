<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Concerns;

use Throwable;

trait ManagesTransactions
{
    private bool $transEnabled = true;

    private bool $transStrict = true;

    private int $transDepth = 0;

    private bool $transStatus = true;

    private bool $transFailure = false;

    public function trans_off(): void
    {
        $this->transEnabled = false;
    }

    public function trans_strict(bool $mode = true): void
    {
        $this->transStrict = $mode;
    }

    public function trans_start(bool $testMode = false): bool
    {
        if (!$this->transEnabled) {
            return false;
        }

        return $this->trans_begin($testMode);
    }

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

    public function trans_status(): bool
    {
        return $this->transStatus;
    }

    public function trans_active(): bool
    {
        return $this->transDepth > 0;
    }

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

    protected function markTransactionFailure(): void
    {
        $this->transStatus = false;
    }

    abstract protected function beginTransaction(): void;

    abstract protected function commitTransaction(): void;

    abstract protected function rollbackTransaction(): void;
}
