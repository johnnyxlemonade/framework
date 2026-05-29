<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Config;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Config\ConfigLoader;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Framework;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-config-loader-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testLoadWithStrictManifestLoadsConfiguredFiles(): void
    {
        $this->writeConfigFile('App.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['app' => ['name' => 'Demo']];\n");
        $this->writeConfigFile('Logging.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['logging' => ['default' => 'stderr']];\n");
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['App.php' => null, 'Logging.php' => null], 'http' => [], 'cli' => []];\n");

        $loader = new ConfigLoader();
        $context = $this->context();
        $framework = $this->framework($context);

        $loader->loadApplication($framework, $context, ConfigLoader::ENTRYPOINT_HTTP);

        $config = $framework->container()->get(Config::class);

        self::assertSame('Demo', $config->get('app.name'));
        self::assertSame('stderr', $config->get('logging.default'));
    }

    public function testLoadWithEntrypointAwareManifestLoadsSharedAndHttpForHttpEntrypoint(): void
    {
        $this->writeConfigFile(
            'Config.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['Shared.php' => null], 'http' => ['Http.php' => null], 'cli' => ['Cli.php' => null]];\n",
        );
        $this->writeConfigFile('Shared.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['enabled' => true]];\n");
        $this->writeConfigFile('Http.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['http' => ['enabled' => true]];\n");
        $this->writeConfigFile('Cli.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['cli' => ['enabled' => true]];\n");

        $loader = new ConfigLoader();
        $context = $this->context();
        $framework = $this->framework($context);

        $loader->loadApplication($framework, $context, ConfigLoader::ENTRYPOINT_HTTP);

        $config = $framework->container()->get(Config::class);
        self::assertTrue((bool) $config->get('shared.enabled'));
        self::assertTrue((bool) $config->get('http.enabled'));
        self::assertNull($config->get('cli.enabled'));
    }

    public function testLoadWithEntrypointAwareManifestLoadsSharedAndCliForCliEntrypoint(): void
    {
        $this->writeConfigFile(
            'Config.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['Shared.php' => null], 'http' => ['Http.php' => null], 'cli' => ['Cli.php' => null]];\n",
        );
        $this->writeConfigFile('Shared.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['enabled' => true]];\n");
        $this->writeConfigFile('Http.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['http' => ['enabled' => true]];\n");
        $this->writeConfigFile('Cli.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['cli' => ['enabled' => true]];\n");

        $loader = new ConfigLoader();
        $context = $this->context();
        $framework = $this->framework($context);

        $loader->loadApplication($framework, $context, ConfigLoader::ENTRYPOINT_CLI);

        $config = $framework->container()->get(Config::class);
        self::assertTrue((bool) $config->get('shared.enabled'));
        self::assertTrue((bool) $config->get('cli.enabled'));
        self::assertNull($config->get('http.enabled'));
    }

    public function testInvalidManifestNotReturningArrayThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn 'invalid';\n");

        $this->expectException(LogicException::class);

        (new ConfigLoader())->resolveConfigFileSpecs($this->context(), ConfigLoader::ENTRYPOINT_HTTP);
    }

    public function testInvalidManifestWithoutEntrypointKeysThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['invalid' => []];\n");

        $this->expectException(LogicException::class);

        (new ConfigLoader())->resolveConfigFileSpecs($this->context(), ConfigLoader::ENTRYPOINT_HTTP);
    }

    public function testInvalidManifestSectionNotArrayThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => [], 'http' => 'invalid', 'cli' => []];\n");

        $this->expectException(LogicException::class);

        (new ConfigLoader())->resolveConfigFileSpecs($this->context(), ConfigLoader::ENTRYPOINT_HTTP);
    }

    public function testInvalidManifestFileItemNotStringThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => [123 => null], 'http' => [], 'cli' => []];\n");

        $this->expectException(LogicException::class);

        (new ConfigLoader())->resolveConfigFileSpecs($this->context(), ConfigLoader::ENTRYPOINT_HTTP);
    }

    public function testInvalidManifestFileItemEmptyStringThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['' => null], 'http' => [], 'cli' => []];\n");

        $this->expectException(LogicException::class);

        (new ConfigLoader())->resolveConfigFileSpecs($this->context(), ConfigLoader::ENTRYPOINT_HTTP);
    }

    public function testInvalidManifestRootKeyThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['Api.php' => ''], 'http' => [], 'cli' => []];\n");

        $this->expectException(LogicException::class);

        (new ConfigLoader())->resolveConfigFileSpecs($this->context(), ConfigLoader::ENTRYPOINT_HTTP);
    }

    public function testRootKeyMappingWrapsSectionWithoutDoubleNesting(): void
    {
        $this->writeConfigFile(
            'Config.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['Cache.php' => 'cache'], 'http' => [], 'cli' => []];\n",
        );
        $this->writeConfigFile(
            'Cache.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['default' => 'file'];\n",
        );

        $loader = new ConfigLoader();
        $context = $this->context();
        $framework = $this->framework($context);

        $loader->loadApplication($framework, $context, ConfigLoader::ENTRYPOINT_HTTP);

        $config = $framework->container()->get(Config::class);
        self::assertSame('file', $config->string('cache.default'));
        self::assertNull($config->get('cache.cache.default'));
    }

    public function testRootKeyMappingThrowsWhenFileAlreadyContainsSameRootKey(): void
    {
        $this->writeConfigFile(
            'Config.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['Cache.php' => 'cache'], 'http' => [], 'cli' => []];\n",
        );
        $this->writeConfigFile(
            'Cache.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['cache' => ['default' => 'file']];\n",
        );

        $loader = new ConfigLoader();
        $context = $this->context();
        $framework = $this->framework($context);

        $this->expectException(LogicException::class);
        $loader->loadApplication($framework, $context, ConfigLoader::ENTRYPOINT_HTTP);
    }

    private function context(): ApplicationContext
    {
        return new ApplicationContext(
            Environment::Testing,
            new Path($this->root),
            DebugMode::disabled(),
        );
    }

    private function framework(ApplicationContext $context): Framework
    {
        return new Framework(new Container(), $context);
    }

    private function configDir(): string
    {
        return $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config';
    }

    private function writeConfigFile(string $file, string $contents): void
    {
        $configDir = $this->configDir();

        if (!is_dir($configDir)) {
            mkdir($configDir, 0775, true);
        }

        file_put_contents($configDir . DIRECTORY_SEPARATOR . $file, $contents);
    }

    private function deleteRecursive(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $items = scandir($path);

        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->deleteRecursive($path . DIRECTORY_SEPARATOR . $item);
        }

        @rmdir($path);
    }
}
