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

    public function testLoadWithoutManifestLoadsExistingAndSkipsMissingConventionalFiles(): void
    {
        $this->writeConfigFile('App.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['app' => ['name' => 'Demo']];\n");
        $this->writeConfigFile('Logging.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['logging' => ['default' => 'stderr']];\n");

        $loader = new ConfigLoader();
        $context = $this->context();
        $framework = $this->framework($context);

        $loader->load($framework, $context, ['App.php', 'Missing.php', 'Logging.php']);

        $config = $framework->container()->get(Config::class);

        self::assertSame('Demo', $config->get('app.name'));
        self::assertSame('stderr', $config->get('logging.default'));
    }

    public function testLoadWithValidManifestUsesManifestFilesInsteadOfConventionalFallback(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['files' => ['Alt.php']];\n");
        $this->writeConfigFile('Alt.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['alt' => ['enabled' => true]];\n");
        $this->writeConfigFile('App.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['app' => ['name' => 'ShouldNotLoad']];\n");

        $loader = new ConfigLoader();
        $context = $this->context();
        $framework = $this->framework($context);

        $loader->load($framework, $context, ['App.php']);

        $config = $framework->container()->get(Config::class);

        self::assertTrue((bool) $config->get('alt.enabled'));
        self::assertNull($config->get('app.name'));
    }

    public function testInvalidManifestNotReturningArrayThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn 'invalid';\n");

        $this->expectException(LogicException::class);

        (new ConfigLoader())->resolveConfigFileNames($this->context(), ['App.php']);
    }

    public function testInvalidManifestWithoutFilesKeyThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['invalid' => []];\n");

        $this->expectException(LogicException::class);

        (new ConfigLoader())->resolveConfigFileNames($this->context(), ['App.php']);
    }

    public function testInvalidManifestFilesNotArrayThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['files' => 'invalid'];\n");

        $this->expectException(LogicException::class);

        (new ConfigLoader())->resolveConfigFileNames($this->context(), ['App.php']);
    }

    public function testInvalidManifestFileItemNotStringThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['files' => [123]];\n");

        $this->expectException(LogicException::class);

        (new ConfigLoader())->resolveConfigFileNames($this->context(), ['App.php']);
    }

    public function testInvalidManifestFileItemEmptyStringThrowsLogicException(): void
    {
        $this->writeConfigFile('Config.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['files' => ['']];\n");

        $this->expectException(LogicException::class);

        (new ConfigLoader())->resolveConfigFileNames($this->context(), ['App.php']);
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
