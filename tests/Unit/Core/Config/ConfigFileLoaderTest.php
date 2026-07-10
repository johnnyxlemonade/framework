<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Config;

use Lemonade\Framework\Core\Config\AppConfigDefinition;
use Lemonade\Framework\Core\Config\ConfigFileLoader;
use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;
use Lemonade\Framework\Core\Config\ProvidersConfigDefinition;
use Lemonade\Framework\Core\Config\Yaml\YamlConfigParser;
use Lemonade\Framework\Core\Config\Yaml\YamlDefinitionClassMap;
use Lemonade\Framework\Core\Config\Yaml\YamlDefinitionLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigFileLoaderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-config-file-loader-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_BASE_URL']);
        $this->deleteRecursive($this->root);
    }

    public function testLoadYamlFileReturnsTypedDefinition(): void
    {
        $_ENV['APP_BASE_URL'] = 'https://yaml.example.test';

        $file = $this->writeFile('App.yaml', <<<'YAML'
module: app
config:
  base_url:
    $env: APP_BASE_URL
    type: string
    default: http://localhost
  timezone: Europe/Prague
YAML);

        $definition = (new ConfigFileLoader())->load($file);

        self::assertInstanceOf(AppConfigDefinition::class, $definition);
        self::assertSame([
            'base_url' => 'https://yaml.example.test',
            'timezone' => 'Europe/Prague',
        ], $definition->toArray());
    }

    public function testLoadYamlFileUsesSiblingConfigMapForCustomDefinition(): void
    {
        $this->writeFile('ConfigMap.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'Custom' => \Lemonade\Framework\Tests\Unit\Core\Config\TestCustomConfigDefinition::class,
];
PHP);
        $file = $this->writeFile('Custom.yaml', <<<'YAML'
module: custom
config:
  enabled: true
  endpoint: https://example.test
YAML);

        $definition = (new ConfigFileLoader())->load($file);

        self::assertInstanceOf(TestCustomConfigDefinition::class, $definition);
        self::assertSame([
            'enabled' => true,
            'endpoint' => 'https://example.test',
        ], $definition->toArray());
    }

    public function testYamlLoaderThrowsClearExceptionWhenParserMissing(): void
    {
        $file = $this->writeFile('App.yaml', "module: app\nconfig: {}\n");

        $loader = new YamlDefinitionLoader(
            new YamlConfigParser('Definitely\\Missing\\YamlParser'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('YAML parser is not available');

        $loader->load($file, null, YamlDefinitionClassMap::withDefaults());
    }

    public function testYamlLoaderThrowsClearExceptionForInvalidYaml(): void
    {
        $file = $this->writeFile('App.yaml', "module: app\nconfig: [\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid YAML');

        (new ConfigFileLoader())->load($file);
    }

    public function testYamlLoaderThrowsClearExceptionForUnknownMapping(): void
    {
        $file = $this->writeFile('Unknown.yaml', <<<'YAML'
module: unknown
config:
  enabled: true
YAML);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No YAML config definition mapping found');

        (new ConfigFileLoader())->load($file);
    }

    public function testYamlLoaderThrowsClearExceptionForInvalidPayload(): void
    {
        $file = $this->writeFile('App.yaml', <<<'YAML'
module: app
config: invalid
YAML);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must define "config" as a mapping');

        (new ConfigFileLoader())->load($file);
    }

    public function testLoadProvidersYamlReturnsFlatProviderList(): void
    {
        $file = $this->writeFile('Providers.yaml', <<<'YAML'
module: providers
config:
  providers:
    - Lemonade\Framework\Tests\Unit\Core\Config\TestProviderA
    - Lemonade\Framework\Tests\Unit\Core\Config\TestProviderB
YAML);

        $definition = (new ConfigFileLoader())->load($file);

        self::assertInstanceOf(ProvidersConfigDefinition::class, $definition);
        self::assertSame([
            TestProviderA::class,
            TestProviderB::class,
        ], $definition->toArray());
    }

    private function writeFile(string $file, string $contents): string
    {
        if (!is_dir($this->root)) {
            mkdir($this->root, 0775, true);
        }

        $path = $this->root . DIRECTORY_SEPARATOR . $file;
        file_put_contents($path, $contents);

        return $path;
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

final class TestCustomConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'custom';
    }
}

final class TestProviderA implements \Lemonade\Framework\Core\ServiceProviderInterface
{
    public function register(\Lemonade\Framework\Container\ContainerInterface $container): void
    {
        unset($container);
    }
}

final class TestProviderB implements \Lemonade\Framework\Core\ServiceProviderInterface
{
    public function register(\Lemonade\Framework\Container\ContainerInterface $container): void
    {
        unset($container);
    }
}
