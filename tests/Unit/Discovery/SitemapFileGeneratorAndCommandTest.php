<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Discovery;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Discovery\Console\GenerateSitemapCommand;
use Lemonade\Framework\Discovery\Sitemap\RouteSitemapProvider;
use Lemonade\Framework\Discovery\Sitemap\SitemapFileGenerator;
use Lemonade\Framework\Discovery\Sitemap\SitemapGenerator;
use Lemonade\Framework\Discovery\Sitemap\SitemapIndexGenerator;
use Lemonade\Framework\Discovery\Sitemap\SitemapProviderInterface;
use Lemonade\Framework\Discovery\Sitemap\SitemapProviderRegistry;
use Lemonade\Framework\Discovery\Sitemap\SitemapUrl;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Support\BaseUrlResolver;
use PHPUnit\Framework\TestCase;

final class SitemapFileGeneratorAndCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-discovery-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testCacheGenerationCreatesFileAndCommandReturnsZero(): void
    {
        $router = new Router();
        $router->getNamed('home', '/', 'HomeController@index');
        $config = new Config([
            'app' => ['base_url' => 'https://example.com'],
            'discovery' => [
                'sitemap' => [
                    'routes' => ['home'],
                    'providers' => [],
                    'cache_path' => 'storage/cache/discovery',
                    'filename' => 'sitemap.xml',
                    'index_filename' => 'sitemap.xml',
                    'max_urls_per_file' => 50000,
                    'gzip' => false,
                ],
            ],
        ]);
        $context = new ApplicationContext(Environment::Testing, new Path($this->root), DebugMode::disabled());

        $routeProvider = new RouteSitemapProvider($config, new UrlGenerator($router));
        $registry = new SitemapProviderRegistry(new \Lemonade\Framework\Container\Container(), $config, $routeProvider);
        $generator = new SitemapGenerator($registry, new BaseUrlResolver($config), $config);
        $fileGenerator = new SitemapFileGenerator(
            $generator,
            new SitemapIndexGenerator(),
            $config,
            $context,
            new DirectoryManager(),
        );

        $result = $fileGenerator->generate();
        self::assertFileExists($result->indexFile()->path());

        $stdout = fopen('php://temp', 'w+b');
        $stderr = fopen('php://temp', 'w+b');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        $command = new GenerateSitemapCommand($fileGenerator, $config, $context, $stdout, $stderr);
        self::assertSame(0, $command->run([]));
        fclose($stdout);
        fclose($stderr);
    }

    public function testGenerationCleansTemporaryFilesOnFailure(): void
    {
        $router = new Router();
        $router->getNamed('home', '/', 'HomeController@index');
        $config = new Config([
            'app' => ['base_url' => 'https://example.com'],
            'discovery' => [
                'sitemap' => [
                    'routes' => ['home'],
                    'providers' => [FailingSitemapProvider::class],
                    'cache_path' => 'storage/cache/discovery',
                    'filename' => 'sitemap.xml',
                    'index_filename' => 'sitemap.xml',
                ],
            ],
        ]);
        $container = new \Lemonade\Framework\Container\Container();
        $container->singleton(FailingSitemapProvider::class, FailingSitemapProvider::class);
        $context = new ApplicationContext(Environment::Testing, new Path($this->root), DebugMode::disabled());

        $routeProvider = new RouteSitemapProvider($config, new UrlGenerator($router));
        $registry = new SitemapProviderRegistry($container, $config, $routeProvider);
        $generator = new SitemapGenerator($registry, new BaseUrlResolver($config), $config);
        $fileGenerator = new SitemapFileGenerator(
            $generator,
            new SitemapIndexGenerator(),
            $config,
            $context,
            new DirectoryManager(),
        );

        $this->expectException(\RuntimeException::class);
        try {
            $fileGenerator->generate();
        } finally {
            $cachePath = $this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'discovery';
            $tmpFiles = glob($cachePath . DIRECTORY_SEPARATOR . '*.tmp');
            self::assertTrue($tmpFiles === false || $tmpFiles === []);
        }
    }

    public function testGzipModeGeneratesGzipChunksAndXmlIndex(): void
    {
        $router = new Router();
        $router->getNamed('home', '/', 'HomeController@index');
        $router->getNamed('examples.index', '/examples', 'ExamplesController@index');
        $config = new Config([
            'app' => ['base_url' => 'https://example.com'],
            'discovery' => [
                'sitemap' => [
                    'routes' => ['home', 'examples.index'],
                    'providers' => [],
                    'cache_path' => 'storage/cache/discovery',
                    'filename' => 'sitemap.xml',
                    'index_filename' => 'sitemap.xml',
                    'max_urls_per_file' => 1,
                    'gzip' => true,
                ],
            ],
        ]);
        $context = new ApplicationContext(Environment::Testing, new Path($this->root), DebugMode::disabled());
        $routeProvider = new RouteSitemapProvider($config, new UrlGenerator($router));
        $registry = new SitemapProviderRegistry(new \Lemonade\Framework\Container\Container(), $config, $routeProvider);
        $generator = new SitemapGenerator($registry, new BaseUrlResolver($config), $config);
        $fileGenerator = new SitemapFileGenerator(
            $generator,
            new SitemapIndexGenerator(),
            $config,
            $context,
            new DirectoryManager(),
        );

        $result = $fileGenerator->generate();
        self::assertStringEndsWith('.xml', $result->indexFile()->path());
        self::assertGreaterThan(1, count($result->files()));
        foreach ($result->files() as $file) {
            self::assertStringEndsWith('.gz', $file->path());
        }
    }

    public function testMaxUncompressedBytesSplitsSitemapParts(): void
    {
        $router = new Router();
        $router->getNamed('home', '/', 'HomeController@index');
        $router->getNamed('examples.index', '/examples', 'ExamplesController@index');
        $config = new Config([
            'app' => ['base_url' => 'https://example.com'],
            'discovery' => [
                'sitemap' => [
                    'routes' => ['home', 'examples.index'],
                    'providers' => [],
                    'cache_path' => 'storage/cache/discovery',
                    'filename' => 'sitemap.xml',
                    'index_filename' => 'sitemap.xml',
                    'max_urls_per_file' => 50000,
                    'max_uncompressed_bytes' => 80,
                    'gzip' => false,
                ],
            ],
        ]);
        $context = new ApplicationContext(Environment::Testing, new Path($this->root), DebugMode::disabled());
        $routeProvider = new RouteSitemapProvider($config, new UrlGenerator($router));
        $registry = new SitemapProviderRegistry(new \Lemonade\Framework\Container\Container(), $config, $routeProvider);
        $generator = new SitemapGenerator($registry, new BaseUrlResolver($config), $config);
        $fileGenerator = new SitemapFileGenerator(
            $generator,
            new SitemapIndexGenerator(),
            $config,
            $context,
            new DirectoryManager(),
        );

        $result = $fileGenerator->generate();
        self::assertGreaterThan(1, count($result->files()));
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

final class FailingSitemapProvider implements SitemapProviderInterface
{
    public function urls(): iterable
    {
        yield SitemapUrl::create('/ok');
        throw new \RuntimeException('Simulated provider failure');
    }
}
