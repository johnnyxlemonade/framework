<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Health;

use DateTimeZone;
use JsonException;
use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiRoutePathResolver;
use Lemonade\Framework\Api\Http\Response\ApiResponseFactory;
use Lemonade\Framework\Clock\SystemClock;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\FrameworkInfo;
use Lemonade\Framework\Http\Config\CorsConfig;
use Lemonade\Framework\Http\Middleware\CorsMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;

/**
 * Serves an eligible public framework health request before the full HTTP bootstrap runs.
 *
 * Unsupported, unmatched or protected requests return null and continue through the standard lifecycle.
 */
final readonly class FrameworkHealthFastPath
{
    /**
     * Binds pre-bootstrap health handling to loaded definitions.
     */
    public function __construct(
        private ConfigDefinitionRegistry $definitions,
        private ApiRoutePathResolver $pathResolver = new ApiRoutePathResolver(),
    ) {
    }

    /**
     * Returns a complete health response when the request is eligible, or null to defer normal dispatch.
     *
     * @throws JsonException When the health payload cannot be encoded as JSON.
     * @throws RuntimeException When the configured application timezone is invalid.
     */
    public function tryHandle(ServerRequestInterface $request): ?ResponseInterface
    {
        if (!$this->isSupportedMethod($request->getMethod())) {
            return null;
        }

        $snapshot = (new FrameworkHealthConfigSnapshotLoader($this->definitions))->load();
        if (!$snapshot instanceof FrameworkHealthConfigSnapshot) {
            return null;
        }

        $apiConfig = $snapshot->api();
        if (!$this->matchesHealthRequest($request, $apiConfig)) {
            return null;
        }

        if ($apiConfig->framework()->health()->access() !== ApiAccess::Public) {
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
                'timestamp' => (new SystemClock($this->resolveTimezone($snapshot->app())))->now()->format(DATE_ATOM),
            ],
        )->withHeader(
            $frameworkInfo->poweredByHeader(),
            $frameworkInfo->poweredByValue(),
        );

        $cors = $snapshot->cors();
        if ($cors instanceof CorsConfig) {
            $response = (new CorsMiddleware($cors, $responseFactory))->process(
                $request,
                new class ($response) implements RequestHandlerInterface {
                    /**
                     * Captures the response that CORS middleware must decorate without dispatching a controller.
                     */
                    public function __construct(
                        private readonly ResponseInterface $response,
                    ) {
                    }

                    /**
                     * Returns the captured health response unchanged to the surrounding middleware.
                     */
                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        unset($request);

                        return $this->response;
                    }
                },
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
        $framework = $apiConfig->framework();
        $health = $framework->health();

        if (!$apiConfig->enabled() || !$health->enabled()) {
            return false;
        }

        $expectedPath = $this->pathResolver->compose(
            $apiConfig->prefix(),
            $health->route(),
        );
        $requestPath = $this->pathResolver->normalizePath($request->getUri()->getPath());

        return $requestPath === $expectedPath;
    }

    private function resolveTimezone(AppConfig $config): ?DateTimeZone
    {
        $value = $config->timezone();
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
