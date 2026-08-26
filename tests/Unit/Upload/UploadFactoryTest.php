<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Upload;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Upload\Config\UploadConfigDefinition;
use Lemonade\Framework\Upload\Config\UploadConfigResolver;
use Lemonade\Framework\Upload\FileUploadValidator;
use Lemonade\Framework\Upload\Image\GdImageProcessor;
use Lemonade\Framework\Upload\ImageUploadValidator;
use Lemonade\Framework\Upload\Mime\MimeTypeDetector;
use Lemonade\Framework\Upload\Storage\UploadStorage;
use Lemonade\Framework\Upload\UploadFactory;
use Lemonade\Framework\Upload\UploadService;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class UploadFactoryTest extends TestCase
{
    public function testFileOptionsUseResolvedPublicUploadsDirectoryInSeparatedWebrootMode(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path('/var/www/framework', '/var/www/framework/public'),
            DebugMode::disabled(),
        );

        $factory = $this->factory($context);
        $options = $factory->fileOptions();

        self::assertSame('/var/www/framework/public/uploads/images', $options->targetDirectory());
        self::assertSame('uploads/images', $options->targetRelativeDirectory());
    }

    public function testFileOptionsUseLegacyPublicBaseWhenNoDedicatedPublicPathExists(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path('C:\\laragon\\www\\framework', 'C:\\laragon\\www\\framework'),
            DebugMode::disabled(),
        );

        $factory = $this->factory($context);
        $options = $factory->fileOptions();

        self::assertSame('C:\\laragon\\www\\framework\\uploads\\images', $options->targetDirectory());
        self::assertSame('uploads/images', $options->targetRelativeDirectory());
    }

    private function factory(ApplicationContext $context): UploadFactory
    {
        $config = (new UploadConfigResolver())->resolve(
            UploadConfigDefinition::create()->fileProfile(
                profile: 'default',
                targetDirectory: 'images',
                maxBytes: 1024,
                allowedMimeTypes: ['image/png'],
            ),
        );
        $translator = new UploadFactoryTranslatorStub();
        $fileValidator = new FileUploadValidator($translator);
        $directoryManager = new DirectoryManager();

        return new UploadFactory(
            config: $config,
            service: new UploadService(
                $fileValidator,
                new ImageUploadValidator($fileValidator, $translator),
                new UploadStorage(
                    $translator,
                    new Filesystem(
                        $directoryManager,
                        new FileManager(),
                        new LockManager($directoryManager),
                    ),
                ),
                new MimeTypeDetector($translator),
                new GdImageProcessor($translator),
            ),
            request: new ServerRequest('POST', '/upload'),
            translator: $translator,
            context: $context,
        );
    }
}

final class UploadFactoryTranslatorStub implements TranslatorInterface
{
    public function setLocale(?string $locale): self
    {
        unset($locale);

        return $this;
    }

    public function locale(): ?string
    {
        return null;
    }

    public function get(string $key, array $replacements = [], ?string $locale = null): string
    {
        unset($replacements, $locale);

        return $key;
    }

    public function group(string $group, ?string $locale = null): array
    {
        unset($locale);

        return [$group => $group];
    }

    public function all(?string $locale = null): array
    {
        unset($locale);

        return ['messages' => ['ok' => 'ok']];
    }
}
