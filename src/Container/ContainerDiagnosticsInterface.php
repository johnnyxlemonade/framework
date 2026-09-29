<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

use Psr\Log\LoggerInterface;

/** @internal Runtime diagnostic configuration for framework bootstrap. */
interface ContainerDiagnosticsInterface
{
    public function setDiagnosticLogger(?LoggerInterface $logger): void;
}
