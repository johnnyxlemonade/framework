<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\KernelFactory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class KernelFactoryTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-kernel-factory-' . uniqid('', true);
        $configDir = $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config';

        if (!is_dir($configDir)) {
            mkdir($configDir, 0775, true);
        }

        file_put_contents(
            $configDir . DIRECTORY_SEPARATOR . 'Config.yaml',
            "shared:\n  - App\n  - Api\nhttp: []\ncli: []\n",
        );
        file_put_contents(
            $configDir . DIRECTORY_SEPARATOR . 'App.yaml',
            "module: app\nconfig: {}\n",
        );
        file_put_contents(
            $configDir . DIRECTORY_SEPARATOR . 'Api.yaml',
            "module: api\nconfig: {}\n",
        );
        file_put_contents(
            $configDir . DIRECTORY_SEPARATOR . 'Routing.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Routing\\Router;\n\nreturn static function (Router \$router): void {\n};\n",
        );
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testCreateBuildsKernelWithHealthFastPathDependency(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root),
            DebugMode::disabled(),
        );

        $kernel = (new KernelFactory())->create($context);
        $response = $kernel->run(new ServerRequest('GET', '/api/framework/health'));

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($kernel->container()->isBound(\Lemonade\Framework\Http\Middleware\MiddlewareStack::class));
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
