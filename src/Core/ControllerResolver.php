<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Controller\ControllerActionInspector;
use Lemonade\Framework\Core\Controller\ControllerArgumentBinder;
use Lemonade\Framework\Core\Controller\ControllerResultNormalizer;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Routing\RouteMatch;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

/**
 * Route-to-controller dispatcher for HTTP requests.
 *
 * The resolver obtains controller instances through the dependency injection
 * container, resolves the current scoped PSR-7 server request for the dispatch cycle,
 * resolves action parameters from the request and route parameters, performs
 * scalar conversion for builtin parameter types, invokes the controller
 * action, and normalizes the result to a PSR-7 response.
 */
final class ControllerResolver
{
    private readonly ControllerActionInspector $actionInspector;

    private readonly ControllerArgumentBinder $argumentBinder;

    private readonly ControllerResultNormalizer $resultNormalizer;

    /**
     * Accepts the scoped container used for controller resolution,
     * response factories, stream factories, and benchmark access.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Benchmark $benchmark,
    ) {
        $this->actionInspector = new ControllerActionInspector();
        $this->argumentBinder = new ControllerArgumentBinder();
        $this->resultNormalizer = new ControllerResultNormalizer();
    }

    /**
     * Dispatches the matched controller action and normalizes its result to a PSR-7 response.
     *
     * The current request is already bound into the active scope by the kernel.
     * The resolver then reads the controller class, action, and route
     * parameters from the route match, resolves the controller through the
     * container, initializes {@see AbstractController} context when applicable,
     * injects `ServerRequestInterface` action parameters directly, maps named
     * route parameters to action arguments, converts builtin `int`, `float`,
     * `bool`, and `string` parameters, applies declared default values when
     * route parameters are absent, invokes the action, and normalizes the
     * result.
     *
     * Existing PSR responses are returned unchanged. Scalar, stringable, and
     * `null` results are converted to a `200 text/html; charset=UTF-8`
     * response.
     *
     * @param RouteMatch $match Matched route carrying the controller class, action, and named route parameters.
     * @param ServerRequestInterface $request Current request used for dispatch and optional action injection.
     *
     * @throws RuntimeException If the controller class is empty, the resolved controller is not an object,
     *     the action is missing, a required action parameter cannot be resolved, scalar parameter conversion fails,
     *     or the controller action result cannot be normalized to a supported response type.
     */
    public function handle(RouteMatch $match, ServerRequestInterface $request): PsrResponseInterface
    {
        $controllerClass = $match->controller();
        $action = $match->action();
        $controllerClass = trim($controllerClass);
        if ($controllerClass === '') {
            throw new RuntimeException('Resolved controller class must be a non-empty string.');
        }
        /** @var non-empty-string $controllerClass */

        $this->markBenchmark('controller_resolve_start');
        $controller = $this->container->get($controllerClass);
        if (!is_object($controller)) {
            throw new RuntimeException(sprintf(
                'Resolved controller "%s" must be an object.',
                $controllerClass,
            ));
        }

        $resolvedAction = $this->actionInspector->inspect($controller, $controllerClass, $action);

        if ($controller instanceof AbstractController) {
            /** @var ResponseFactoryInterface $responseFactory */
            $responseFactory = $this->container->get(ResponseFactoryInterface::class);
            /** @var StreamFactoryInterface $streamFactory */
            $streamFactory = $this->container->get(StreamFactoryInterface::class);
            $controller->setControllerContext($request, $responseFactory, $streamFactory, $this->container);
        }

        $this->markBenchmark('controller_resolved');
        $args = $this->argumentBinder->bind($resolvedAction, $match, $request);

        $this->markBenchmark('controller_action_start');
        $result = $resolvedAction->method()->invokeArgs($resolvedAction->controller(), $args);
        $this->markBenchmark('controller_action_finished');

        $response = $this->resultNormalizer->normalize(
            result: $result,
            responseFactoryResolver: function (): ResponseFactoryInterface {
                /** @var ResponseFactoryInterface $responseFactory */
                $responseFactory = $this->container->get(ResponseFactoryInterface::class);

                return $responseFactory;
            },
            streamFactoryResolver: function (): StreamFactoryInterface {
                /** @var StreamFactoryInterface $streamFactory */
                $streamFactory = $this->container->get(StreamFactoryInterface::class);

                return $streamFactory;
            },
        );
        $this->markBenchmark('response_created');

        return $response;
    }

    private function markBenchmark(string $name): void
    {
        $run = $this->benchmark->current();
        if ($run === null) {
            return;
        }

        $run->mark($name);
    }
}
