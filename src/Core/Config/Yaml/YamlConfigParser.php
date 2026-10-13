<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config\Yaml;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Parses YAML configuration files through the installed YAML parser.
 */
final readonly class YamlConfigParser
{
    /**
     * Receives the YAML parser class used to read configuration files.
     */
    public function __construct(
        private string $parserClass = Yaml::class,
    ) {
    }

    /**
     * Parses one YAML configuration file into its native PHP representation.
     */
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
