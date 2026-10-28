<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Cache\CacheServiceProvider;
use Lemonade\Framework\Cli\ConsoleServiceProvider;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\AppConfigDefinition;
use Lemonade\Framework\Core\Config\ConfigLoader;
use Lemonade\Framework\Core\Config\FrameworkConfig;
use Lemonade\Framework\Core\Config\ProvidersConfig;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Logging\LoggingServiceProvider;
use Lemonade\Framework\Filesystem\FilesystemServiceProvider;
use Lemonade\Framework\Http\HttpServiceProvider;
use Lemonade\Framework\Observability\Http\HttpCheckpoint;
use Lemonade\Framework\Observability\Http\Internal\HttpInstrumentation;

/**
 * Coordinates the shared root-container bootstrap for application entrypoints.
 *
 * Configuration is deliberately loaded separately from the complete bootstrap:
 * the HTTP kernel needs its configuration before creating the request scope and
 * evaluating the health fast-path.
 *
 * @internal
 */
final class ApplicationBootstrapper
{
    /** @var array<string, true> */
    private array $loadedConfigurations = [];

    /**
     * Shares the root bootstrap services and the HTTP boundary without starting a request.
     */
    public function __construct(
        private readonly ApplicationContext $context,
        private readonly ContainerInterface $container,
        private readonly Framework $framework,
        private readonly HttpInstrumentation $diagnostics,
    ) {
    }

    /**
     * Loads configuration definitions for one entrypoint at most once.
     */
    public function loadConfiguration(BootstrapEntrypoint $entrypoint): void
    {
        if (isset($this->loadedConfigurations[$entrypoint->value])) {
            return;
        }

        (new ConfigLoader())->loadApplication(
            $this->framework,
            $this->context,
            match ($entrypoint) {
                BootstrapEntrypoint::Http => ConfigLoader::ENTRYPOINT_HTTP,
                BootstrapEntrypoint::Cli => ConfigLoader::ENTRYPOINT_CLI,
            },
        );

        $this->loadedConfigurations[$entrypoint->value] = true;
        $this->diagnostics->checkpoint(HttpCheckpoint::ConfigurationLoaded);
    }

    /**
     * Performs the root-container bootstrap after entrypoint configuration has loaded.
     */
    public function bootstrap(BootstrapEntrypoint $entrypoint): void
    {
        if ($entrypoint === BootstrapEntrypoint::Http) {
            $this->diagnostics->checkpoint(HttpCheckpoint::BootstrapStarted);
        }

        $this->loadConfiguration($entrypoint);
        $this->applyRuntimeAppConfig();
        $plan = $this->providerPlan($entrypoint);
        $this->framework->registerPlan($plan);
        $this->diagnostics->checkpoint(HttpCheckpoint::ProvidersRegistered);

        $this->framework->bootProviders();
        $this->diagnostics->checkpoint(HttpCheckpoint::ProvidersBooted);

        $this->registerRoutes($entrypoint);
        $this->framework->finalizeRoutes();

        if ($entrypoint === BootstrapEntrypoint::Http) {
            $this->diagnostics->checkpoint(HttpCheckpoint::RoutesRegistered);
        }
    }

    private function applyRuntimeAppConfig(): void
    {
        $this->framework->config(
            AppConfigDefinition::create()
                ->basePath($this->context->basePath())
                ->publicPath($this->context->publicPath())
                ->env($this->context->environment()->value)
                ->debug($this->context->debug())
                ->appPath($this->context->appPath())
                ->configPath($this->context->configPath())
                ->storagePath($this->context->storagePath()),
        );
    }

    private function providerPlan(BootstrapEntrypoint $entrypoint): ProviderLifecyclePlan
    {
        return new ProviderLifecyclePlan([
            new CoreServiceProvider(),
            new FilesystemServiceProvider(),
            new CacheServiceProvider(),
            new LoggingServiceProvider(),
            $this->primaryProvider($entrypoint),
            ...$this->commonFrameworkProviders(),
            ...$this->configuredProviders(),
        ]);
    }

    private function primaryProvider(BootstrapEntrypoint $entrypoint): object
    {
        return match ($entrypoint) {
            BootstrapEntrypoint::Http => new HttpServiceProvider(),
            BootstrapEntrypoint::Cli => new ConsoleServiceProvider(),
        };
    }

    /** @return list<object> */
    private function commonFrameworkProviders(): array
    {
        $providerClasses = $this->container->get(FrameworkConfig::class)->providers;

        return $this->providerInstances($providerClasses);
    }

    /** @return list<object> */
    private function configuredProviders(): array
    {
        $providerClasses = $this->container->get(ProvidersConfig::class)->providers;

        return $this->providerInstances($providerClasses);
    }

    /**
     * @param list<class-string> $providerClasses
     * @return list<object>
     */
    private function providerInstances(array $providerClasses): array
    {
        $providers = [];
        $factory = new ProviderFactory($this->container);

        foreach ($providerClasses as $providerClass) {
            $providers[] = $factory->create($providerClass);
        }

        return $providers;
    }

    private function registerRoutes(BootstrapEntrypoint $entrypoint): void
    {
        $routingConfig = $this->context->configPath('Routing.php');

        if ($entrypoint === BootstrapEntrypoint::Cli && !is_file($routingConfig)) {
            return;
        }

        $this->framework->routesFromFile($routingConfig);
    }

}
