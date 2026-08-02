<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Config;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Config\ConfigLoader;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Framework;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-config-loader-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testLoadWithYamlManifestLoadsYamlConfigsAndResolvesRuntimeDto(): void
    {
        $this->writeConfigFile(
            'Config.yaml',
            <<<'YAML'
shared:
  - App
  - Api
http:
  - HtmlMinify
cli: []
YAML,
        );
        $this->writeConfigFile(
            'App.yaml',
            <<<'YAML'
module: app
config:
  base_url: https://example.test
YAML,
        );
        $this->writeConfigFile(
            'Api.yaml',
            <<<'YAML'
module: api
config:
  prefix: /yaml-api
  framework:
    docs:
      enabled: true
YAML,
        );
        $this->writeConfigFile(
            'HtmlMinify.yaml',
            <<<'YAML'
module: html_minify
config:
  enabled: true
YAML,
        );

        $loader = new ConfigLoader();
        $context = $this->context();
        $framework = $this->framework($context);

        $loader->loadApplication($framework, $context, ConfigLoader::ENTRYPOINT_HTTP);

        $config = $framework->container()->get(Config::class);
        self::assertSame('https://example.test', $config->string('app.base_url'));
        self::assertSame('/yaml-api', $config->string('api.prefix'));
        self::assertTrue($config->bool('api.framework.docs.enabled'));
        self::assertTrue($config->bool('html_minify.enabled'));
        $registry = $framework->container()->get(ConfigDefinitionRegistry::class);
        $apiConfig = (new \Lemonade\Framework\Api\Config\ApiConfigResolver())->resolve(
            ...$registry->typedEntriesFor(
                \Lemonade\Framework\Api\Config\ApiConfigDefinition::moduleKey(),
                \Lemonade\Framework\Api\Config\ApiConfigDefinition::class,
            ),
        );
        self::assertSame('/yaml-api', $apiConfig->prefix);
    }

    public function testLoadWithEntrypointAwareManifestLoadsSharedAndHttpForHttpEntrypoint(): void
    {
        $this->writeConfigFile(
            'Config.yaml',
            <<<'YAML'
shared:
  - App
http:
  - HtmlMinify
cli:
  - Commands
YAML,
        );
        $this->writeConfigFile(
            'App.yaml',
            <<<'YAML'
module: app
config:
  base_url: https://shared.test
YAML,
        );
        $this->writeConfigFile(
            'HtmlMinify.yaml',
            <<<'YAML'
module: html_minify
config:
  enabled: true
YAML,
        );
        $this->writeConfigFile(
            'Commands.yaml',
            <<<'YAML'
module: commands
config:
  commands: []
YAML,
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
            'Config.yaml',
            <<<'YAML'
shared:
  - App
http:
  - HtmlMinify
cli:
  - Commands
YAML,
        );
        $this->writeConfigFile(
            'App.yaml',
            <<<'YAML'
module: app
config:
  base_url: https://shared.test
YAML,
        );
        $this->writeConfigFile(
            'HtmlMinify.yaml',
            <<<'YAML'
module: html_minify
config:
  enabled: true
YAML,
        );
        $this->writeConfigFile(
            'Commands.yaml',
            <<<'YAML'
module: commands
config:
  commands: []
YAML,
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

    public function testMissingYamlConfigFileIsSkipped(): void
    {
        $this->writeConfigFile(
            'Config.yaml',
            <<<'YAML'
shared:
  - Api
http: []
cli: []
YAML,
        );

        $framework = $this->framework($this->context());
        (new ConfigLoader())->loadApplication($framework, $this->context(), ConfigLoader::ENTRYPOINT_HTTP);

        self::assertSame('/api', $framework->container()->get(Config::class)->string('api.prefix'));
    }

    public function testInvalidManifestNotReturningArrayThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.yaml', "invalid\n");

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must contain a YAML mapping');

        (new ConfigLoader())->resolveConfigFileSpecs($this->context(), ConfigLoader::ENTRYPOINT_HTTP);
    }

    public function testInvalidYamlManifestNotReturningMappingThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.yaml', "invalid\n");

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must contain a YAML mapping');

        (new ConfigLoader())->resolveConfigFileSpecs($this->context(), ConfigLoader::ENTRYPOINT_HTTP);
    }

    public function testInvalidManifestWithoutEntrypointKeysThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.yaml', "invalid: []\n");

        $this->expectException(LogicException::class);

        (new ConfigLoader())->resolveConfigFileSpecs($this->context(), ConfigLoader::ENTRYPOINT_HTTP);
    }

    public function testInvalidManifestSectionNotArrayThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.yaml', "shared: []\nhttp: invalid\ncli: []\n");

        $this->expectException(LogicException::class);

        (new ConfigLoader())->resolveConfigFileSpecs($this->context(), ConfigLoader::ENTRYPOINT_HTTP);
    }

    public function testInvalidManifestFileItemNotStringThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.yaml', "shared:\n  - 123\nhttp: []\ncli: []\n");

        $this->expectException(LogicException::class);

        (new ConfigLoader())->resolveConfigFileSpecs($this->context(), ConfigLoader::ENTRYPOINT_HTTP);
    }

    public function testInvalidManifestFileItemEmptyStringThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.yaml', "shared:\n  - ''\nhttp: []\ncli: []\n");

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
