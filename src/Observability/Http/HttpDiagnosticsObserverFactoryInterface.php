<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Http;

use Lemonade\Framework\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Creates an optional request-local observer before application providers bootstrap.
 *
 * Implementations must be bootstrap-safe and return null when profiling is disabled.
 * A factory may be supplied explicitly before configuration or implemented by an explicitly
 * configured application provider, activated after configuration and before provider bootstrap.
 */
interface HttpDiagnosticsObserverFactoryInterface
{
    /**
     * Receives the common monotonic origin; defer dependencies needing providers until completion.
     */
    public function create(
        ContainerInterface $container,
        ServerRequestInterface $request,
        int|float $startedNs,
    ): ?HttpDiagnosticsObserverInterface;
}
