<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

/**
 * Allows a database-backed handler to cooperatively extend its current delivery lease.
 *
 * The capability exists only inside the Job scope of a renewable asynchronous transport. It does not interrupt
 * a handler automatically when ownership is lost.
 */
interface QueueLeaseInterface
{
    /**
     * Extends the current live reservation and returns its new database Unix expiry time.
     *
     * @throws Exception\QueueLeaseLostException When the lease expired or another worker owns the job.
     */
    public function renew(): int;
}
