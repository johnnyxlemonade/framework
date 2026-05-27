<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Cache\CacheServiceProvider;
use Lemonade\Framework\Core\Logging\LoggingServiceProvider;
use Lemonade\Framework\Filesystem\FilesystemServiceProvider;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Psr\Log\LoggerInterface;

trait KernelBootstrapTrait
{
    private function applyRuntimeAppConfig(): void
    {
        $this->framework->config([
            'app' => [
                'base_path' => $this->context->basePath(),
                'env' => $this->context->environment()->value,
                'debug' => $this->context->debug(),
                'app_path' => $this->context->appPath(),
                'config_path' => $this->context->configPath(),
                'storage_path' => $this->context->storagePath(),
            ],
        ]);
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
        $config = $this->container->get(Config::class);
        $providers = $config->get('framework.providers');

        if (!is_array($providers)) {
            throw new \LogicException(
                'Config key "framework.providers" must be an array. Make sure the framework default config is loaded.',
            );
        }

        $validated = [];

        foreach ($providers as $providerClass) {
            if (!is_string($providerClass) || !class_exists($providerClass)) {
                throw new \LogicException(sprintf(
                    'Configured framework service provider "%s" does not exist.',
                    is_scalar($providerClass) ? (string) $providerClass : get_debug_type($providerClass),
                ));
            }

            if (!is_subclass_of($providerClass, ServiceProviderInterface::class)) {
                throw new \LogicException(sprintf(
                    'Configured framework service provider "%s" must implement %s.',
                    $providerClass,
                    ServiceProviderInterface::class,
                ));
            }

            /** @var class-string<ServiceProviderInterface> $providerClass */
            $validated[] = $providerClass;
        }

        return $validated;
    }

    private function registerConfiguredProviders(): void
    {
        $config = $this->container->get(Config::class);

        $providers = $config->get('providers', []);

        if (!is_array($providers)) {
            throw new \LogicException('Config key "providers" must be an array.');
        }

        foreach ($providers as $providerClass) {
            if (!is_string($providerClass) || !class_exists($providerClass)) {
                throw new \LogicException(sprintf(
                    'Configured service provider "%s" does not exist.',
                    is_scalar($providerClass) ? (string) $providerClass : get_debug_type($providerClass),
                ));
            }

            $provider = new $providerClass();

            if (!$provider instanceof ServiceProviderInterface) {
                throw new \LogicException(sprintf(
                    'Configured service provider "%s" must implement %s.',
                    $providerClass,
                    ServiceProviderInterface::class,
                ));
            }

            $this->framework->register($provider);
        }
    }

    private function resolveLogFile(string $file): string
    {
        if ($this->isAbsolutePath($file)) {
            return $file;
        }

        return $this->context->storagePath($file);
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
