<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Health;

use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Api\Config\ApiConfigDefinition;
use Lemonade\Framework\Api\Config\ApiConfigResolver;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Core\Config\AppConfigDefinition;
use Lemonade\Framework\Core\Config\AppConfigResolver;
use Lemonade\Framework\Core\Config\ApplicationConfigCache;
use Lemonade\Framework\Core\Config\ConfigLoader;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Http\Config\CorsConfig;
use Lemonade\Framework\Http\Config\CorsConfigDefinition;
use Lemonade\Framework\Http\Config\CorsConfigResolver;
use Lemonade\Framework\Observability\Benchmark\Config\BenchmarkConfig;
use Lemonade\Framework\Observability\Benchmark\Config\BenchmarkConfigDefinition;
use Lemonade\Framework\Observability\Benchmark\Config\BenchmarkConfigResolver;

final class FrameworkHealthConfigSnapshotLoader
{
    public function __construct(
        private readonly ApplicationContext $context,
    ) {}

    public function load(): ?FrameworkHealthConfigSnapshot
    {
        $definitions = $this->loadDefinitions();
        if ($definitions === []) {
            return null;
        }

        $apiConfig = $this->resolveApiConfig($definitions);
        $appConfig = $this->resolveAppConfig($definitions);
        if (!$apiConfig instanceof ApiConfig || !$appConfig instanceof AppConfig) {
            return null;
        }

        return new FrameworkHealthConfigSnapshot(
            api: $apiConfig,
            app: $appConfig,
            cors: $this->resolveCorsConfig($definitions),
            benchmark: $this->resolveBenchmarkConfig($definitions),
        );
    }

    /**
     * @return list<ConfigDefinitionInterface>
     */
    private function loadDefinitions(): array
    {
        if ($this->context->isProduction()) {
            $cached = (new ApplicationConfigCache())->loadIfFresh(
                $this->context,
                ConfigLoader::ENTRYPOINT_HTTP,
            );

            if ($cached !== null) {
                return $cached;
            }
        }

        $container = new Container();
        $framework = new Framework($container, $this->context);
        (new ConfigLoader())->loadApplication($framework, $this->context, ConfigLoader::ENTRYPOINT_HTTP);

        $registry = $container->get(ConfigDefinitionRegistry::class);

        return [
            ...$registry->entriesFor(ApiConfigDefinition::moduleKey()),
            ...$registry->entriesFor(AppConfigDefinition::moduleKey()),
            ...$registry->entriesFor(CorsConfigDefinition::moduleKey()),
            ...$registry->entriesFor(BenchmarkConfigDefinition::moduleKey()),
        ];
    }

    /**
     * @param list<ConfigDefinitionInterface> $definitions
     */
    private function resolveApiConfig(array $definitions): ?ApiConfig
    {
        $resolved = $this->filterDefinitions($definitions, ApiConfigDefinition::class);
        if ($resolved === []) {
            return null;
        }

        /** @var list<ApiConfigDefinition> $resolved */
        return (new ApiConfigResolver())->resolve(...$resolved);
    }

    /**
     * @param list<ConfigDefinitionInterface> $definitions
     */
    private function resolveAppConfig(array $definitions): ?AppConfig
    {
        $resolved = $this->filterDefinitions($definitions, AppConfigDefinition::class);
        if ($resolved === []) {
            return null;
        }

        /** @var list<AppConfigDefinition> $resolved */
        return (new AppConfigResolver())->resolve(...$resolved);
    }

    /**
     * @param list<ConfigDefinitionInterface> $definitions
     */
    private function resolveCorsConfig(array $definitions): ?CorsConfig
    {
        $resolved = $this->filterDefinitions($definitions, CorsConfigDefinition::class);
        if ($resolved === []) {
            return null;
        }

        /** @var list<CorsConfigDefinition> $resolved */
        return (new CorsConfigResolver())->resolve(...$resolved);
    }

    /**
     * @param list<ConfigDefinitionInterface> $definitions
     */
    private function resolveBenchmarkConfig(array $definitions): BenchmarkConfig
    {
        /** @var list<BenchmarkConfigDefinition> $resolved */
        $resolved = $this->filterDefinitions($definitions, BenchmarkConfigDefinition::class);

        return (new BenchmarkConfigResolver())->resolve(...$resolved);
    }

    /**
     * @template T of ConfigDefinitionInterface
     *
     * @param list<ConfigDefinitionInterface> $definitions
     * @param class-string<T> $definitionClass
     * @return list<T>
     */
    private function filterDefinitions(array $definitions, string $definitionClass): array
    {
        $filtered = array_values(array_filter(
            $definitions,
            static fn(ConfigDefinitionInterface $definition): bool => $definition instanceof $definitionClass,
        ));

        /** @var list<T> $filtered */
        return $filtered;
    }
}
