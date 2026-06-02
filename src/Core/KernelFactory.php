<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Http\Psr\ResponseEmitter;

final class KernelFactory
{
    public function __construct(
        private readonly ?ContainerInterface $container = null,
        private readonly ?ResponseEmitter $emitter = null,
    ) {}

    public function create(ApplicationContext $context): Kernel
    {
        $container = $this->container ?? new Container();

        $framework = new Framework(
            container: $container,
            context: $context,
        );

        return new Kernel(
            context: $context,
            container: $container,
            framework: $framework,
            emitter: $this->emitter ?? new ResponseEmitter(),
        );
    }
}
