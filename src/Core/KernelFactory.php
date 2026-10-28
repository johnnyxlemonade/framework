<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Health\FrameworkHealthFastPath;
use Lemonade\Framework\Http\Psr\ResponseEmitter;
use Lemonade\Framework\Observability\Http\HttpDiagnosticsObserverFactoryInterface;
use Lemonade\Framework\Observability\Http\Internal\HttpInstrumentation;

/**
 * Factory for assembling the HTTP application kernel.
 *
 * The factory accepts an optional preconfigured dependency injection container
 * and an optional response emitter. When they are not supplied, it creates a
 * default container and response emitter, builds a framework runtime for the
 * provided application context, and returns a fully wired kernel instance.
 */
final readonly class KernelFactory
{
    /**
     * Configures optional runtime dependencies used when creating kernels.
     *
     * The supplied container is reused by both the framework runtime and the
     * resulting kernel. When no container is provided, a default container is
     * created. The supplied emitter is used by {@see Kernel::handle()}, and a
     * default response emitter is created when none is provided. An explicit diagnostics
     * factory may opt into profiling before configuration. Otherwise only explicitly configured
     * application providers implementing the observer factory contract can activate profiling.
     */
    public function __construct(
        private ?ContainerInterface $container = null,
        private ?ResponseEmitter $emitter = null,
        private ?HttpDiagnosticsObserverFactoryInterface $diagnostics = null,
    ) {
    }

    /**
     * Creates a new HTTP kernel for the provided application context.
     *
     * The method reuses or creates a dependency injection container, builds a
     * framework runtime with the same container and context, and then creates a
     * kernel with the same context, container, framework, and response emitter.
     *
     * The created kernel is returned without bootstrapping or running a
     * request.
     */
    public function create(ApplicationContext $context): Kernel
    {
        $container = $this->container ?? new Container();

        $framework = new Framework(
            container: $container,
            context: $context,
            diagnostics: $this->diagnostics,
        );

        return new Kernel(
            context: $context,
            container: $container,
            framework: $framework,
            emitter: $this->emitter ?? new ResponseEmitter(),
            healthFastPath: new FrameworkHealthFastPath(
                $container->get(ConfigDefinitionRegistry::class),
            ),
            diagnostics: $container->get(HttpInstrumentation::class),
        );
    }
}
