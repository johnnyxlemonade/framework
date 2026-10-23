<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

/**
 * Extends a queue transport with cooperative renewal of an already owned delivery lease.
 */
interface RenewableQueueTransportInterface extends QueueTransportInterface
{
    /**
     * Extends a still-valid reservation and returns its new database Unix expiry time.
     *
     * @throws Exception\QueueLeaseLostException When the delivery no longer owns a live reservation.
     */
    public function renew(QueuedMessage $message): int;
}
