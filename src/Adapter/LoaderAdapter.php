<?php

declare(strict_types=1);

namespace Lemonade\Framework\Adapter;

use Lemonade\Framework\Container\ContainerInterface;
use RuntimeException;

final class LoaderAdapter
{
    public function __construct(private readonly ContainerInterface $container) {}

    public function service(string $id): mixed
    {
        $id = trim($id);
        if ($id === '') {
            throw new RuntimeException('Service id must be a non-empty string.');
        }
        /** @var non-empty-string $id */

        return $this->container->get($id);
    }

    public function helper(string $helperId): mixed
    {
        return $this->service($helperId);
    }

    public function model(string $modelClass): object
    {
        $service = $this->service($modelClass);
        if (!is_object($service)) {
            throw new RuntimeException(sprintf('Models "%s" is not an object service.', $modelClass));
        }

        return $service;
    }
}
