<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container\Exception;

final class SingletonDependsOnScopedServiceException extends ContainerException {}
