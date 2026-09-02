<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Health;

use DateTimeZone;
use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiRoutePathResolver;
use Lemonade\Framework\Api\Http\Response\ApiResponseFactory;
use Lemonade\Framework\Clock\SystemClock;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\FrameworkInfo;
use Lemonade\Framework\Http\Config\CorsConfig;
use Lemonade\Framework\Http\Middleware\CorsMiddleware;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Observability\Benchmark\BenchmarkResponseInjector;
use Lemonade\Framework\Observability\Benchmark\BenchmarkRun;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;

final class FrameworkHealthFastPath
{
    public function __construct(
        private readonly ApplicationContext $context,
        private readonly ApiRoutePathResolver $pathResolver = new ApiRoutePathResolver(),
    ) {}

    public function tryHandle(ServerRequestInterface $request, ?Benchmark $benchmark = null): ?ResponseInterface
    {
        if (!$this->isSupportedMethod($request->getMethod())) {
            return null;
        }

        $benchmark?->currentOrStart()->mark('health_fast_path_start');

        $snapshot = (new FrameworkHealthConfigSnapshotLoader($this->context))->load();
        if (!$snapshot instanceof FrameworkHealthConfigSnapshot) {
            return null;
        }

        $benchmark?->currentOrStart()->mark('config_loaded');

        if (!$this->matchesHealthRequest($request, $snapshot->api)) {
            return null;
        }

        if ($snapshot->api->framework->health->access !== ApiAccess::Public) {
            return null;
        }

        $responseFactory = new Psr17Factory();
        $frameworkInfo = new FrameworkInfo();
        $response = (new ApiResponseFactory($responseFactory))->ok(
            [
                'status' => 'ok',
                'service' => $frameworkInfo->name(),
                'version' => $frameworkInfo->version(),
            ],
            [
                'timestamp' => (new SystemClock($this->resolveTimezone($snapshot->app)))->now()->format(DATE_ATOM),
            ],
        )->withHeader(
            $frameworkInfo->poweredByHeader(),
            $frameworkInfo->poweredByValue(),
        );

        if ($snapshot->cors instanceof CorsConfig) {
            $response = (new CorsMiddleware($snapshot->cors, $responseFactory))->process(
                $request,
                new class ($response) implements RequestHandlerInterface {
                    public function __construct(
                        private readonly ResponseInterface $response,
                    ) {}

                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        unset($request);

                        return $this->response;
                    }
                },
            );
        }

        $benchmark?->currentOrStart()->mark('response_created');
        $benchmark?->currentOrStart()->mark('response_ready');
        $run = $benchmark?->current();
        $run?->stop();

        if ($run instanceof BenchmarkRun) {
            $response = (new BenchmarkResponseInjector($snapshot->benchmark))->inject(
                $response,
                $run,
            );
        }

        return $response;
    }

    private function isSupportedMethod(string $method): bool
    {
        $method = strtoupper($method);

        return $method === 'GET' || $method === 'HEAD';
    }

    private function matchesHealthRequest(ServerRequestInterface $request, ApiConfig $apiConfig): bool
    {
        if (!$apiConfig->enabled || !$apiConfig->framework->health->enabled) {
            return false;
        }

        $expectedPath = $this->pathResolver->compose(
            $apiConfig->prefix,
            $apiConfig->framework->health->route,
        );
        $requestPath = $this->pathResolver->normalizePath($request->getUri()->getPath());

        return $requestPath === $expectedPath;
    }

    private function resolveTimezone(AppConfig $config): ?DateTimeZone
    {
        $value = $config->timezone;
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeZone($value);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Invalid configured timezone in app.timezone: "%s".', $value),
                0,
                $exception,
            );
        }
    }

}
