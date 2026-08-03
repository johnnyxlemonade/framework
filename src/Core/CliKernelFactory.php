<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;

/**
 * Factory for constructing CLI application kernels.
 *
 * The factory accepts an optional preconfigured container and optional stdout
 * and stderr streams. When no container is supplied, it creates a new default
 * container, then wires a `Framework` instance and a `CliKernel` instance
 * around the provided application context. The factory itself does not
 * bootstrap the application or dispatch commands.
 */
final class CliKernelFactory
{
    /** @var resource|null */
    private readonly mixed $stdout;

    /** @var resource|null */
    private readonly mixed $stderr;

    /**
     * Creates a factory with optional container and output stream overrides.
     *
     * The container is reused for both the framework and the resulting CLI
     * kernel. When stdout or stderr is omitted, the created kernel falls back
     * to its default stream handling.
     *
     * @throws \InvalidArgumentException If stdout or stderr is not a valid resource.
     */
    public function __construct(
        private readonly ?ContainerInterface $container = null,
        mixed $stdout = null,
        mixed $stderr = null,
    ) {
        if ($stdout !== null && !is_resource($stdout)) {
            throw new \InvalidArgumentException('CliKernelFactory stdout must be a valid resource.');
        }
        if ($stderr !== null && !is_resource($stderr)) {
            throw new \InvalidArgumentException('CliKernelFactory stderr must be a valid resource.');
        }

        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }

    /**
     * Creates a new CLI kernel for the given application context.
     *
     * The factory uses the provided container or creates a new default
     * container, builds a framework instance over the same context and
     * container, and returns a CLI kernel that shares the same context,
     * container, framework, and configured streams. The method does not
     * bootstrap the kernel or run a command.
     */
    public function create(ApplicationContext $context): CliKernel
    {
        $container = $this->container ?? new Container();

        $framework = new Framework(
            container: $container,
            context: $context,
        );

        return new CliKernel(
            context: $context,
            container: $container,
            framework: $framework,
            stdout: $this->stdout,
            stderr: $this->stderr,
        );
    }
}
