<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Driver\Concerns;

use Lemonade\Framework\Database\Driver\Concerns\ManagesTransactions;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ManagesTransactionsTest extends TestCase
{
    public function testTransactionLifecycleAndNestedDepth(): void
    {
        $driver = new class {
            use ManagesTransactions;

            public int $beginCalls = 0;
            public int $commitCalls = 0;
            public int $rollbackCalls = 0;
            public bool $failBegin = false;
            public bool $failCommit = false;
            public bool $failRollback = false;

            protected function beginTransaction(): void
            {
                if ($this->failBegin) {
                    throw new RuntimeException('begin');
                }
                $this->beginCalls++;
            }

            protected function commitTransaction(): void
            {
                if ($this->failCommit) {
                    throw new RuntimeException('commit');
                }
                $this->commitCalls++;
            }

            protected function rollbackTransaction(): void
            {
                if ($this->failRollback) {
                    throw new RuntimeException('rollback');
                }
                $this->rollbackCalls++;
            }

            public function simulateQueryFailure(): void
            {
                $this->markTransactionFailure();
            }
        };

        self::assertTrue($driver->trans_start());
        self::assertTrue($driver->trans_active());
        self::assertSame(1, $driver->beginCalls);

        self::assertTrue($driver->trans_start());
        self::assertTrue($driver->trans_active());
        self::assertSame(1, $driver->beginCalls);

        self::assertTrue($driver->trans_complete());
        self::assertTrue($driver->trans_active());
        self::assertSame(0, $driver->commitCalls);

        self::assertTrue($driver->trans_complete());
        self::assertFalse($driver->trans_active());
        self::assertSame(1, $driver->commitCalls);
    }

    public function testTransCompleteRollsBackInTestModeAndStrictFalseResetsStatus(): void
    {
        $driver = new class {
            use ManagesTransactions;

            public int $rollbackCalls = 0;

            protected function beginTransaction(): void {}

            protected function commitTransaction(): void {}

            protected function rollbackTransaction(): void
            {
                $this->rollbackCalls++;
            }

            public function simulateQueryFailure(): void
            {
                $this->markTransactionFailure();
            }
        };

        self::assertTrue($driver->trans_start(true));
        self::assertFalse($driver->trans_complete());
        self::assertSame(1, $driver->rollbackCalls);

        $driver->trans_strict(false);
        self::assertTrue($driver->trans_start());
        $driver->trans_begin();
        $driver->simulateQueryFailure();
        self::assertFalse($driver->trans_complete());
        self::assertTrue($driver->trans_status());
    }

    public function testBeginCommitRollbackFailuresReturnFalse(): void
    {
        $driver = new class {
            use ManagesTransactions;

            public bool $failBegin = false;
            public bool $failCommit = false;
            public bool $failRollback = false;

            protected function beginTransaction(): void
            {
                if ($this->failBegin) {
                    throw new RuntimeException('begin');
                }
            }

            protected function commitTransaction(): void
            {
                if ($this->failCommit) {
                    throw new RuntimeException('commit');
                }
            }

            protected function rollbackTransaction(): void
            {
                if ($this->failRollback) {
                    throw new RuntimeException('rollback');
                }
            }

            public function failStatus(): void
            {
                $this->markTransactionFailure();
            }
        };

        $driver->failBegin = true;
        self::assertFalse($driver->trans_begin());
        $driver->failBegin = false;

        self::assertTrue($driver->trans_begin());
        $driver->failCommit = true;
        self::assertFalse($driver->trans_commit());
        $driver->failCommit = false;

        $driver->failStatus();
        $driver->failRollback = true;
        self::assertFalse($driver->trans_complete());
    }
}
