<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Image;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Filesystem\DirectoryPathGenerator;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use Lemonade\Framework\Image\Contract\ImageVariantRendererInterface;
use Lemonade\Framework\Image\ImageVariantCache;
use Lemonade\Framework\Image\ImageVariantGenerator;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Image\Value\EncodedImage;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalReference;
use Lemonade\Framework\Image\Value\ImageOriginalStorage;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageSource;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use PHPUnit\Framework\TestCase;

final class ImageVariantGeneratorTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/lemonade-generator-' . uniqid('', true);
        mkdir($this->root . '/public', 0775, true);
    }

    protected function tearDown(): void
    {
        (new DirectoryManager())->delete($this->root);
    }

    public function testRendererIsTheSinglePassBoundaryAndCacheHitSkipsIt(): void
    {
        $paths = new ImageVariantPathResolver(
            new ApplicationContext(
                Environment::Testing,
                new Path($this->root, $this->root . '/public'),
                DebugMode::disabled(),
            ),
            new DirectoryPathGenerator(),
        );
        $directory = new DirectoryManager();
        $cache = new ImageVariantCache(
            new Filesystem(
                $directory,
                new FileManager(),
                new LockManager($directory),
            ),
            $paths,
        );
        $renderer = new RecordingVariantRenderer();
        $generator = new ImageVariantGenerator($cache, $paths, $renderer);
        $asset = new ImageAsset(
            'asset',
            new ImageOriginalReference(
                ImageOriginalStorage::Storage,
                'original.jpg',
                'v1',
                ImageFormat::Jpeg,
                new ImageDimensions(100, 50),
            ),
        );
        $definition = new ImageVariantDefinition(
            new ImageDimensions(64, 64),
            ImageFormat::Png,
            ImageQuality::fromInt(80),
        );

        $generator->generate($asset, $definition);
        $generator->generate($asset, $definition);

        self::assertSame(1, $renderer->calls);
        self::assertSame($paths->originalPath($asset), $renderer->source?->path());
        self::assertSame($asset->original(), $renderer->original);
        self::assertSame($definition, $renderer->definition);
    }
}

final class RecordingVariantRenderer implements ImageVariantRendererInterface
{
    public int $calls = 0;
    public ?ImageSource $source = null;
    public ?ImageOriginalReference $original = null;
    public ?ImageVariantDefinition $definition = null;

    public function render(
        ImageSource $source,
        ImageOriginalReference $original,
        ImageVariantDefinition $definition,
    ): EncodedImage {
        $this->calls++;
        $this->source = $source;
        $this->original = $original;
        $this->definition = $definition;

        return new EncodedImage('rendered', $definition->format(), $definition->dimensions());
    }
}
