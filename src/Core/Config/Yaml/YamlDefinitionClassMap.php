<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config\Yaml;

use Lemonade\Framework\Api\Config\ApiConfigDefinition;
use Lemonade\Framework\Cache\Config\CacheConfigDefinition;
use Lemonade\Framework\Cli\Config\CommandsConfigDefinition;
use Lemonade\Framework\Component\Breadcrumb\Config\BreadcrumbsConfigDefinition;
use Lemonade\Framework\Component\Config\ComponentConfigDefinition;
use Lemonade\Framework\Component\Meta\Config\MetaConfigDefinition;
use Lemonade\Framework\Component\Pagination\Config\PaginationConfigDefinition;
use Lemonade\Framework\Container\Config\ContainerConfigDefinition;
use Lemonade\Framework\Core\Config\AppConfigDefinition;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionInterface;
use Lemonade\Framework\Core\Config\FrameworkConfigDefinition;
use Lemonade\Framework\Core\Config\IntegrationsConfigDefinition;
use Lemonade\Framework\Core\Config\ProvidersConfigDefinition;
use Lemonade\Framework\Core\Logging\Config\LoggingConfigDefinition;
use Lemonade\Framework\Database\Config\DatabaseConfigDefinition;
use Lemonade\Framework\Discovery\Config\DiscoveryConfigDefinition;
use Lemonade\Framework\Event\Config\EventsConfigDefinition;
use Lemonade\Framework\Http\Config\CorsConfigDefinition;
use Lemonade\Framework\Http\Config\ErrorConfigDefinition;
use Lemonade\Framework\Http\Config\HtmlMinifyConfigDefinition;
use Lemonade\Framework\Http\Config\HttpClientConfigDefinition;
use Lemonade\Framework\Localization\Config\LocalizationConfigDefinition;
use Lemonade\Framework\Observability\Benchmark\Config\BenchmarkConfigDefinition;
use Lemonade\Framework\Queue\Config\QueueConfigDefinition;
use Lemonade\Framework\Session\Config\SessionConfigDefinition;
use Lemonade\Framework\Upload\Config\UploadConfigDefinition;
use Lemonade\Framework\View\Config\ViewConfigDefinition;
use LogicException;

final class YamlDefinitionClassMap
{
    /**
     * @var array<string, class-string<ConfigDefinitionInterface>>
     */
    private array $classesByAlias = [];

    /**
     * @var array<string, class-string<ConfigDefinitionInterface>>
     */
    private array $classesByModule = [];

    public static function withDefaults(): self
    {
        $map = new self();

        $map->register('App', AppConfigDefinition::class);
        $map->register('Framework', FrameworkConfigDefinition::class);
        $map->register('Providers', ProvidersConfigDefinition::class);
        $map->register('Container', ContainerConfigDefinition::class);
        $map->register('Api', ApiConfigDefinition::class);
        $map->register('Benchmark', BenchmarkConfigDefinition::class);
        $map->register('Cache', CacheConfigDefinition::class);
        $map->register('Commands', CommandsConfigDefinition::class);
        $map->register('Components', ComponentConfigDefinition::class);
        $map->register('Breadcrumbs', BreadcrumbsConfigDefinition::class);
        $map->register('Cors', CorsConfigDefinition::class);
        $map->register('Database', DatabaseConfigDefinition::class);
        $map->register('Discovery', DiscoveryConfigDefinition::class);
        $map->register('Error', ErrorConfigDefinition::class);
        $map->register('Events', EventsConfigDefinition::class);
        $map->register('HtmlMinify', HtmlMinifyConfigDefinition::class);
        $map->register('HttpClient', HttpClientConfigDefinition::class);
        $map->register('Integrations', IntegrationsConfigDefinition::class);
        $map->register('Localization', LocalizationConfigDefinition::class);
        $map->register('Logging', LoggingConfigDefinition::class);
        $map->register('Meta', MetaConfigDefinition::class);
        $map->register('Pagination', PaginationConfigDefinition::class);
        $map->register('Queue', QueueConfigDefinition::class);
        $map->register('Session', SessionConfigDefinition::class);
        $map->register('Upload', UploadConfigDefinition::class);
        $map->register('View', ViewConfigDefinition::class);

        return $map;
    }

    /**
     * @param array<string, class-string<ConfigDefinitionInterface>> $classesByAlias
     */
    public function registerMany(array $classesByAlias): self
    {
        foreach ($classesByAlias as $alias => $definitionClass) {
            $this->register($alias, $definitionClass);
        }

        return $this;
    }

    /**
     * @param class-string<ConfigDefinitionInterface> $definitionClass
     */
    public function register(string $alias, string $definitionClass): self
    {
        $normalizedAlias = self::normalize($alias);
        if ($normalizedAlias === '') {
            throw new LogicException('YAML config definition alias must not be empty.');
        }

        $this->assertDefinitionClass($definitionClass);

        $this->classesByAlias[$normalizedAlias] = $definitionClass;
        $this->classesByModule[self::normalize($definitionClass::moduleKey())] = $definitionClass;

        return $this;
    }

    /**
     * @return class-string<ConfigDefinitionInterface>|null
     */
    public function resolve(?string $alias, ?string $module): ?string
    {
        $moduleKey = self::normalize((string) $module);
        if ($moduleKey !== '' && isset($this->classesByModule[$moduleKey])) {
            return $this->classesByModule[$moduleKey];
        }

        $normalizedAlias = self::normalize((string) $alias);
        if ($normalizedAlias !== '' && isset($this->classesByAlias[$normalizedAlias])) {
            return $this->classesByAlias[$normalizedAlias];
        }

        return null;
    }

    private function assertDefinitionClass(string $definitionClass): void
    {
        if (!class_exists($definitionClass)) {
            throw new LogicException(sprintf(
                'YAML config definition class "%s" does not exist.',
                $definitionClass,
            ));
        }

        if (!is_subclass_of($definitionClass, ConfigDefinitionInterface::class)) {
            throw new LogicException(sprintf(
                'YAML config definition class "%s" must implement %s.',
                $definitionClass,
                ConfigDefinitionInterface::class,
            ));
        }
    }

    private static function normalize(string $value): string
    {
        return strtolower(trim($value));
    }
}
