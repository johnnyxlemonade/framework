<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Cli;

use Lemonade\Framework\Cli\CommandInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Queue\QueueBusInterface;

final class QueueWorkCommand implements CommandInterface
{
    public function __construct(
        private readonly QueueBusInterface $queue,
        private readonly Config $config,
    ) {}

    public function name(): string
    {
        return 'queue:work';
    }

    public function description(): string
    {
        return 'Process queued jobs (database/sync transport) in a worker loop.';
    }

    /**
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        $queueName = $args[0] ?? 'default';
        $transport = $args[1] ?? ($this->config->string('queue.default', 'sync') ?? 'sync');
        $max = isset($args[2]) && is_numeric($args[2]) ? max(0, (int) $args[2]) : 0;
        $sleepMs = isset($args[3]) && is_numeric($args[3]) ? max(50, (int) $args[3]) : 500;

        if ($transport === 'sync') {
            fwrite(STDERR, "queue:work requires async transport (e.g. 'database').\n");

            return 1;
        }

        $processed = 0;

        while (true) {
            $handled = $this->queue->processNext($queueName, $transport);

            if ($handled) {
                $processed++;
                fwrite(STDOUT, sprintf("[queue] processed=%d queue=%s\n", $processed, $queueName));

                if ($max > 0 && $processed >= $max) {
                    break;
                }

                continue;
            }

            if ($max > 0 && $processed >= $max) {
                break;
            }

            usleep($sleepMs * 1000);
        }

        return 0;
    }
}
