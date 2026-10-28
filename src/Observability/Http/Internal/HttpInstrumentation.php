<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Http\Internal;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Config\ProvidersConfig;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Logging\Config\LoggingConfig;
use Lemonade\Framework\Core\Logging\Config\LoggingConfigDefinition;
use Lemonade\Framework\Core\Logging\Config\LoggingConfigResolver;
use Lemonade\Framework\Core\Logging\LogFilePathResolver;
use Lemonade\Framework\Core\Logging\LogManager;
use Lemonade\Framework\Core\ProviderFactory;
use Lemonade\Framework\Database\Instrumentation\DatabaseObserverInterface;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseInstrumentation;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseMeasurements;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Http\Logging\HttpLogContext;
use Lemonade\Framework\Http\Logging\HttpRequestLogger;
use Lemonade\Framework\Http\Psr\ServerRequestFactory;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\Observability\Http\Config\HttpDiagnosticsConfig;
use Lemonade\Framework\Observability\Http\HttpCheckpoint;
use Lemonade\Framework\Observability\Http\HttpDiagnosticsHeaders;
use Lemonade\Framework\Observability\Http\HttpDiagnosticsObserverFactoryInterface;
use Lemonade\Framework\Observability\Http\HttpDiagnosticsObserverInterface;
use Lemonade\Framework\Observability\Http\HttpMetrics;
use Lemonade\Framework\Routing\RouteMatch;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Runs the HTTP entrypoint's operation and publishes one summary after its request scope closes.
 *
 * @internal This is a synchronous boundary shared by Kernel and standalone Framework entrypoints,
 * with no application timing API. It retains neither metrics nor checkpoint history between calls.
 */
final class HttpInstrumentation
{
    private bool $handling = false;
    private int|float $startedNs = 0;
    private ?HttpDiagnosticsObserverInterface $observer = null;
    private ?RouteMatch $match = null;

    /**
     * Shares the database instrument with root-owned connections and accepts one optional profiler factory.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly DatabaseInstrumentation $database,
        private readonly ?HttpDiagnosticsObserverFactoryInterface $observerFactory = null,
    ) {
    }

    /**
     * Includes configuration, dispatch, error conversion and scope cleanup in one monotonic interval.
     *
     * The dispatch callback owns and closes its scope before returning. The failure callback maps
     * application errors to responses; diagnostics failures cannot replace those responses.
     *
     * @param callable(): void $prepare
     * @param callable(ServerRequestInterface): ResponseInterface $dispatch
     * @param callable(Throwable): ResponseInterface $failure
     */
    public function handle(
        ?ServerRequestInterface &$request,
        callable $prepare,
        callable $dispatch,
        callable $failure,
    ): ResponseInterface {
        if ($this->handling) {
            throw new LogicException('An HTTP request is already active on this runtime.');
        }

        $this->handling = true;
        $this->startedNs = hrtime(true);
        $config = new HttpDiagnosticsConfig();
        $logging = null;
        $sql = null;

        $response = null;
        $unhandled = null;

        try {
            try {
                $request ??= $this->container->get(ServerRequestFactory::class)->fromGlobals();
                try {
                    $this->observer = $this->observerFactory?->create($this->container, $request, $this->startedNs);
                } catch (Throwable) {
                    $this->observer = null;
                }
                $this->checkpoint(HttpCheckpoint::RequestStarted, 0.0);

                $work = function () use ($request, $prepare, $dispatch, &$config, &$logging, &$sql): ResponseInterface {
                    $prepare();
                    $definitions = $this->container->get(ConfigDefinitionRegistry::class);
                    $config = $this->container->get(HttpDiagnosticsConfig::class);
                    $logging = (new LoggingConfigResolver())->resolve(...$definitions->typedEntriesFor(
                        LoggingConfigDefinition::moduleKey(),
                        LoggingConfigDefinition::class,
                    ));

                    if ($this->observerFactory === null) {
                        $this->startConfiguredObserver($request);
                    }

                    if (($config->enabled() || $this->observer !== null) && $sql === null) {
                        return $this->database->collect(
                            static function (DatabaseMeasurements $measurements) use (&$sql, $request, $dispatch): ResponseInterface {
                                $sql = $measurements;

                                return $dispatch($request);
                            },
                            $this->observer instanceof DatabaseObserverInterface ? $this->observer : null,
                        );
                    }

                    return $dispatch($request);
                };

                if ($this->observer !== null) {
                    $databaseObserver = $this->observer instanceof DatabaseObserverInterface ? $this->observer : null;
                    $response = $this->database->collect(
                        static function (DatabaseMeasurements $measurements) use (&$sql, $work): ResponseInterface {
                            $sql = $measurements;

                            return $work();
                        },
                        $databaseObserver,
                    );
                } else {
                    $response = $work();
                }
            } catch (Throwable $exception) {
                try {
                    $response = $failure($exception);
                } catch (Throwable $propagated) {
                    // Standalone Framework may preserve an escaping application exception.
                    $unhandled = $propagated;
                }
            }

            if (!$config->enabled() && $this->observer === null && !($logging?->requestEnabled() ?? false)) {
                if ($unhandled !== null) {
                    throw $unhandled;
                }
                assert($response instanceof ResponseInterface);

                return $response;
            }

            $metrics = new HttpMetrics(
                durationMs: (hrtime(true) - $this->startedNs) / 1_000_000,
                phpPeakMemoryBytes: memory_get_peak_usage(false),
                sqlCount: $sql?->queryCount() ?? 0,
                sqlMs: $sql?->queryMs() ?? 0.0,
            );

            if ($response !== null && $request !== null && $logging instanceof LoggingConfig && $logging->requestEnabled()) {
                try {
                    $logs = $this->container instanceof ContainerBuilderInterface && $this->container->isBound(LogManager::class)
                        ? $this->container->get(LogManager::class)
                        : new LogManager(
                            $logging,
                            new LogFilePathResolver($this->container->get(ApplicationContext::class)),
                            new DirectoryManager(),
                        );
                    (new HttpRequestLogger(
                        $logging,
                        $logs,
                        new HttpLogContext(new HttpRequestInspector()),
                    ))->log($request, $response, $metrics);
                } catch (Throwable) {
                    // Optional request logging must preserve the final application response.
                }
            }

            try {
                $this->observer?->completed($metrics);
                if ($response !== null && $request !== null) {
                    $response = $this->observer?->response($request, $response, $this->match) ?? $response;
                }
            } catch (Throwable) {
                // A profiler or its storage is never required to return the application response.
            }

            if ($unhandled !== null) {
                throw $unhandled;
            }
            assert($response instanceof ResponseInterface);

            return (new HttpDiagnosticsHeaders($config))->apply($response, $metrics);
        } finally {
            $this->observer = null;
            $this->match = null;
            $this->startedNs = 0;
            $this->handling = false;
        }
    }

    private function startConfiguredObserver(ServerRequestInterface $request): void
    {
        // Resolve the ordinary provider configuration outside the optional observer failure boundary.
        // A missing configured package must remain a configuration error.
        $providers = $this->container->get(ProvidersConfig::class)->providers;
        foreach ($providers as $providerClass) {
            if (!is_a($providerClass, HttpDiagnosticsObserverFactoryInterface::class, true)) {
                continue;
            }

            $factory = (new ProviderFactory($this->container))->create($providerClass);
            assert($factory instanceof HttpDiagnosticsObserverFactoryInterface);
            try {
                $this->observer = $factory->create($this->container, $request, $this->startedNs);
            } catch (Throwable) {
                $this->observer = null;
            }

            if ($this->observer !== null) {
                $this->checkpoint(HttpCheckpoint::RequestStarted, 0.0);
                return;
            }
        }
    }

    /**
     * Sends a real boundary only to an active observer, without allocating a timeline.
     */
    public function checkpoint(HttpCheckpoint $checkpoint, ?float $elapsedMs = null): void
    {
        if ($this->observer === null) {
            return;
        }

        try {
            $this->observer->checkpoint($checkpoint, $elapsedMs ?? (hrtime(true) - $this->startedNs) / 1_000_000);
        } catch (Throwable) {
            $this->observer = null;
        }
    }

    /**
     * Retains the immutable matched route only until the current observer finishes publishing.
     */
    public function matched(RouteMatch $match): void
    {
        if ($this->observer !== null) {
            $this->match = $match;
        }
    }

    /**
     * Lets request-scoped profiler services share the capture created before bootstrap.
     */
    public function observer(): ?HttpDiagnosticsObserverInterface
    {
        return $this->observer;
    }
}
