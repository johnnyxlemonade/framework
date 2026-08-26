<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Cache\CacheServiceProvider;
use Lemonade\Framework\Core\Config\AppConfigDefinition;
use Lemonade\Framework\Core\Config\FrameworkConfig;
use Lemonade\Framework\Core\Config\ProvidersConfig;
use Lemonade\Framework\Core\Logging\LoggingServiceProvider;
use Lemonade\Framework\Filesystem\FilesystemServiceProvider;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Psr\Log\LoggerInterface;

trait KernelBootstrapTrait
{
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

    private function registerCoreProvidersWithDiagnostics(): void
    {
        $this->framework->register(new CoreServiceProvider());
        $this->framework->register(new FilesystemServiceProvider());
        $this->framework->register(new CacheServiceProvider());
        $this->framework->register(new LoggingServiceProvider());

        $logger = $this->container->get(LoggerInterface::class);
        $this->container->setDiagnosticLogger($logger);
    }

    private function registerCommonFrameworkProviders(): void
    {
        foreach ($this->commonFrameworkProviderClasses() as $providerClass) {
            $this->framework->register(new $providerClass());
        }
    }

    /**
     * @return list<class-string<ServiceProviderInterface>>
     */
    private function commonFrameworkProviderClasses(): array
    {
        return $this->container->get(FrameworkConfig::class)->providers;
    }

    private function registerConfiguredProviders(): void
    {
        foreach ($this->container->get(ProvidersConfig::class)->providers as $providerClass) {
            $provider = new $providerClass();
            $this->framework->register($provider);
        }
    }

    private function resolveLogFile(string $file): string
    {
        if ($this->isAbsolutePath($file)) {
            return $file;
        }

        return $this->context->resolveLogPath($file);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Z]:[\/\\\\]/i', $path) === 1;
    }

    private function benchmark(): ?Benchmark
    {
        if (!$this->container->isBound(Benchmark::class)) {
            return null;
        }

        return $this->container->get(Benchmark::class);
    }

    private function markBenchmark(string $name): void
    {
        $this->benchmark()?->currentOrStart()->mark($name);
    }
}
