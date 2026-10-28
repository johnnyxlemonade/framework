<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Observability\Http;

use Error;
use Lemonade\Framework\Core\Config\ConfigFileLoader;
use Lemonade\Framework\Observability\Http\Config\HttpDiagnosticsConfig;
use Lemonade\Framework\Observability\Http\Config\HttpDiagnosticsConfigDefinition;
use Lemonade\Framework\Observability\Http\Config\HttpDiagnosticsConfigResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class HttpDiagnosticsConfigTest extends TestCase
{
    public function testDefaultsAndOrderedOverrides(): void
    {
        $resolver = new HttpDiagnosticsConfigResolver();
        self::assertFalse((new HttpDiagnosticsConfig())->enabled());
        self::assertFalse($resolver->resolve()->enabled());
        self::assertTrue($resolver->resolve(
            HttpDiagnosticsConfigDefinition::create()->enabled(),
            HttpDiagnosticsConfigDefinition::create(),
        )->enabled());
        self::assertFalse($resolver->resolve(
            HttpDiagnosticsConfigDefinition::create()->enabled(),
            HttpDiagnosticsConfigDefinition::create()->enabled(false),
        )->enabled());
    }

    public function testResolvedConfigRemainsImmutableWhenDefinitionChanges(): void
    {
        $definition = HttpDiagnosticsConfigDefinition::create()->enabled();
        $config = (new HttpDiagnosticsConfigResolver())->resolve($definition);
        $definition->enabled(false);

        self::assertTrue($config->enabled());
        $this->expectException(Error::class);
        (new ReflectionProperty(HttpDiagnosticsConfig::class, 'enabled'))->setValue($config, false);
    }

    public function testInvalidOverrideDoesNotExposeDiagnostics(): void
    {
        $config = (new HttpDiagnosticsConfigResolver())->resolve(
            HttpDiagnosticsConfigDefinition::create()->enabled(),
            HttpDiagnosticsConfigDefinition::fromArrayData(['enabled' => 'invalid']),
        );

        self::assertFalse($config->enabled());
    }

    #[DataProvider('applicationModes')]
    public function testYamlExposureIsIndependentOfApplicationMode(string $environment, string $debug): void
    {
        $originalEnv = $_ENV;
        $file = tempnam(sys_get_temp_dir(), 'http-diagnostics-');
        self::assertIsString($file);
        $yamlFile = $file . '.yaml';

        try {
            $_ENV['APP_ENV'] = $environment;
            $_ENV['APP_DEBUG'] = $debug;
            file_put_contents($yamlFile, "module: http_diagnostics\nconfig:\n  enabled: true\n");
            $definition = (new ConfigFileLoader())->load($yamlFile);
            self::assertInstanceOf(HttpDiagnosticsConfigDefinition::class, $definition);
            $resolver = new HttpDiagnosticsConfigResolver();

            self::assertTrue($resolver->resolve($definition)->enabled());
            self::assertFalse($resolver->resolve()->enabled());
        } finally {
            $_ENV = $originalEnv;
            unlink($file);
            if (is_file($yamlFile)) {
                unlink($yamlFile);
            }
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function applicationModes(): iterable
    {
        yield 'production' => ['production', '0'];
        yield 'production with debug' => ['production', '1'];
        yield 'development' => ['development', '0'];
        yield 'development with debug' => ['development', '1'];
    }
}
