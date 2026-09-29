<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Image;

use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Image\PublicUploadImageUrlResolver;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageReference;
use Lemonade\Framework\Support\BaseUrlResolver;
use PHPUnit\Framework\TestCase;

final class PublicUploadImageUrlResolverTest extends TestCase
{
    public function testResolvesOneCanonicalUploadPrefix(): void
    {
        $context = new ApplicationContext(Environment::Testing, new Path('/framework', '/framework/public'), DebugMode::disabled());
        $base = new BaseUrlResolver(new AppConfig(null, 'https://example.test', '/framework', '/framework/public', 'test', false, '/framework/app', '/framework/app/Config', '/framework/storage'));
        $resolver = new PublicUploadImageUrlResolver($base, $context);
        self::assertSame('https://example.test/uploads/images/variants/a.webp', $resolver->url(new ImageReference('images/variants/a.webp', new ImageDimensions(1, 1), ImageFormat::Webp)));
    }
}
