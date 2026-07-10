<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config\Yaml;

use RuntimeException;
use Throwable;

final class YamlConfigParser
{
    public function __construct(
        private readonly string $parserClass = \Symfony\Component\Yaml\Yaml::class,
    ) {}

    public function parseFile(string $file): mixed
    {
        if (!class_exists($this->parserClass)) {
            throw new RuntimeException(sprintf(
                'YAML parser is not available. Install "symfony/yaml" to load "%s".',
                $file,
            ));
        }

        try {
            /** @var mixed $parsed */
            $parsed = $this->parserClass::parseFile($file);

            return $parsed;
        } catch (Throwable $exception) {
            throw new RuntimeException(sprintf(
                'Invalid YAML in config file "%s": %s',
                $file,
                $exception->getMessage(),
            ), previous: $exception);
        }
    }
}
