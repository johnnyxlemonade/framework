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
use RuntimeException;

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

    public function testLoadWithDefinitionManifestLoadsConfiguredFiles(): void
    {
        $this->writeConfigFile(
            'Config.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['App.php', 'Api.php'], 'http' => ['HtmlMinify.php'], 'cli' => []];\n",
        );
        $this->writeConfigFile(
            'App.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Core\\Config\\AppConfigDefinition;\n\nreturn AppConfigDefinition::create()->baseUrl('https://example.test');\n",
        );
        $this->writeConfigFile(
            'Api.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Api\\Config\\ApiConfigDefinition;\n\nreturn ApiConfigDefinition::create()->prefix('/typed-api')->docsEnabled();\n",
        );
        $this->writeConfigFile(
            'HtmlMinify.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Http\\Config\\HtmlMinifyConfigDefinition;\n\nreturn HtmlMinifyConfigDefinition::create()->enabled();\n",
        );

        $loader = new ConfigLoader();
        $context = $this->context();
        $framework = $this->framework($context);

        $loader->loadApplication($framework, $context, ConfigLoader::ENTRYPOINT_HTTP);

        $config = $framework->container()->get(Config::class);
        self::assertSame('https://example.test', $config->string('app.base_url'));
        self::assertSame('/typed-api', $config->string('api.prefix'));
        self::assertTrue($config->bool('api.framework.docs.enabled'));
        self::assertTrue($config->bool('html_minify.enabled'));
    }

    public function testLoadWithEntrypointAwareManifestLoadsSharedAndHttpForHttpEntrypoint(): void
    {
        $this->writeConfigFile(
            'Config.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['App.php'], 'http' => ['HtmlMinify.php'], 'cli' => ['Commands.php']];\n",
        );
        $this->writeConfigFile(
            'App.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Core\\Config\\AppConfigDefinition;\n\nreturn AppConfigDefinition::create()->baseUrl('https://shared.test');\n",
        );
        $this->writeConfigFile(
            'HtmlMinify.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Http\\Config\\HtmlMinifyConfigDefinition;\n\nreturn HtmlMinifyConfigDefinition::create()->enabled();\n",
        );
        $this->writeConfigFile(
            'Commands.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Cli\\Config\\CommandsConfigDefinition;\n\nreturn CommandsConfigDefinition::create();\n",
        );

        $loader = new ConfigLoader();
        $context = $this->context();
        $framework = $this->framework($context);

        $loader->loadApplication($framework, $context, ConfigLoader::ENTRYPOINT_HTTP);

        $config = $framework->container()->get(Config::class);
        self::assertSame('https://shared.test', $config->string('app.base_url'));
        self::assertTrue($config->bool('html_minify.enabled'));
        self::assertSame([], $config->get('commands', []));
    }

    public function testLoadWithEntrypointAwareManifestLoadsSharedAndCliForCliEntrypoint(): void
    {
        $this->writeConfigFile(
            'Config.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['App.php'], 'http' => ['HtmlMinify.php'], 'cli' => ['Commands.php']];\n",
        );
        $this->writeConfigFile(
            'App.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Core\\Config\\AppConfigDefinition;\n\nreturn AppConfigDefinition::create()->baseUrl('https://shared.test');\n",
        );
        $this->writeConfigFile(
            'HtmlMinify.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Http\\Config\\HtmlMinifyConfigDefinition;\n\nreturn HtmlMinifyConfigDefinition::create()->enabled();\n",
        );
        $this->writeConfigFile(
            'Commands.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Cli\\Config\\CommandsConfigDefinition;\n\nreturn CommandsConfigDefinition::create()->commands([]);\n",
        );

        $loader = new ConfigLoader();
        $context = $this->context();
        $framework = $this->framework($context);

        $loader->loadApplication($framework, $context, ConfigLoader::ENTRYPOINT_CLI);

        $config = $framework->container()->get(Config::class);
        self::assertSame('https://shared.test', $config->string('app.base_url'));
        self::assertNull($config->get('html_minify.enabled'));
        self::assertSame([], $config->get('commands', []));
    }

    public function testConfigFileReturningArrayThrowsExplicitRuntimeException(): void
    {
        $this->writeConfigFile(
            'Config.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['Api.php'], 'http' => [], 'cli' => []];\n",
        );
        $this->writeConfigFile('Api.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['enabled' => true];\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Raw array config is not supported');

        (new ConfigLoader())->loadApplication($this->framework($this->context()), $this->context(), ConfigLoader::ENTRYPOINT_HTTP);
    }

    public function testConfigFileReturningInvalidValueThrowsExplicitRuntimeException(): void
    {
        $this->writeConfigFile(
            'Config.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['Api.php'], 'http' => [], 'cli' => []];\n",
        );
        $this->writeConfigFile('Api.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn 'invalid';\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must return an instance');

        (new ConfigLoader())->loadApplication($this->framework($this->context()), $this->context(), ConfigLoader::ENTRYPOINT_HTTP);
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
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => [123], 'http' => [], 'cli' => []];\n");

        $this->expectException(LogicException::class);

        (new ConfigLoader())->resolveConfigFileSpecs($this->context(), ConfigLoader::ENTRYPOINT_HTTP);
    }

    public function testInvalidManifestFileItemEmptyStringThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => [''], 'http' => [], 'cli' => []];\n");

        $this->expectException(LogicException::class);

        (new ConfigLoader())->resolveConfigFileSpecs($this->context(), ConfigLoader::ENTRYPOINT_HTTP);
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
