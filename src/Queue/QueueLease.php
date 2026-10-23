<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

/**
 * Binds one renewable transport delivery to the handler's isolated Job scope.
 *
 * @internal
 */
final readonly class QueueLease implements QueueLeaseInterface
{
    /**
     * Creates a lease capability for one transport-owned delivery.
     */
    public function __construct(
        private RenewableQueueTransportInterface $transport,
        private QueuedMessage $message,
    ) {
    }

    /**
     * Extends the reservation only while the original delivery still owns it.
     */
    public function renew(): int
    {
        return $this->transport->renew($this->message);
    }
}
