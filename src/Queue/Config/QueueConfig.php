<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Config;

/**
 * Carries a validated queue topology whose default names one configured built-in transport.
 */
final readonly class QueueConfig
{
    /**
     * Lists transport names supported by framework configuration.
     *
     * @var list<'sync'|'database'>
     */
    public const array SUPPORTED_TRANSPORTS = [
        'sync',
        'database',
    ];

    /**
     * Rejects unsupported transports and a default absent from the configured transport set.
     *
     * @param list<string> $transports
     * @param array<string, mixed> $handlers
     * @throws \InvalidArgumentException When a transport is unsupported or the default is not configured.
     */
    public function __construct(
        public string $defaultTransport,
        public array $transports,
        public array $handlers,
        public QueueDatabaseConfig $database,
    ) {
        $this->assertSupportedTransport($defaultTransport, 'Default queue transport');

        foreach ($transports as $transport) {
            $this->assertSupportedTransport($transport, 'Queue transport');
        }

        if (!in_array($defaultTransport, $transports, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Default queue transport "%s" is not present in the configured transports.',
                $defaultTransport,
            ));
        }
    }

    private function assertSupportedTransport(mixed $transport, string $label): void
    {
        if (is_string($transport) && in_array($transport, self::SUPPORTED_TRANSPORTS, true)) {
            return;
        }

        throw new \InvalidArgumentException(sprintf(
            '%s "%s" is unsupported; expected one of: %s.',
            $label,
            is_scalar($transport) ? (string) $transport : get_debug_type($transport),
            implode(', ', self::SUPPORTED_TRANSPORTS),
        ));
    }
}
