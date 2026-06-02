<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;

final class CliKernelFactory
{
    /** @var resource|null */
    private readonly mixed $stdout;

    /** @var resource|null */
    private readonly mixed $stderr;

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
