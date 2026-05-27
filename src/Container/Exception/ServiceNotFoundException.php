<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;

final class ServiceNotFoundException extends ContainerException implements NotFoundExceptionInterface {}
