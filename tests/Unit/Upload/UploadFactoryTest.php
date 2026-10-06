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
use Lemonade\Framework\Image\FilesystemImageFileWriter;
use Lemonade\Framework\Image\Gd\GdCapabilities;
use Lemonade\Framework\Image\Gd\GdImageEncoder;
use Lemonade\Framework\Image\Gd\GdImageProcessor;
use Lemonade\Framework\Upload\ImageUploadValidator;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Mime\MimeTypeDetector;
use Lemonade\Framework\Upload\Storage\UploadStorage;
use Lemonade\Framework\Upload\UploadFactory;
use Lemonade\Framework\Upload\UploadService;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class UploadFactoryTest extends TestCase
{
    public function testDefaultProfilesUseCatalogBackedExtensionPolicies(): void
    {
        $definition = require dirname(__DIR__, 3) . '/src/Config/Upload.php';

        self::assertInstanceOf(UploadConfigDefinition::class, $definition);

        $config = (new UploadConfigResolver())->resolve($definition);

        self::assertSame(['pdf', 'doc', 'docx', 'txt'], $config->files['default']->allowedExtensions);
        self::assertSame(['jpg', 'jpeg', 'png', 'webp'], $config->images['default']->allowedExtensions);
    }

    public function testResolverRejectsLegacyMimePolicies(): void
    {
        $definition = UploadConfigDefinition::fromArrayData([
            'files' => [
                'restricted' => [
                    'target_directory' => 'files',
                    'max_bytes' => 1024,
                    'allowed_extensions' => ['pdf'],
                    'allowed_mime_types' => ['application/pdf'],
                ],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Upload profile "restricted": allowed_mime_types is no longer supported');

        (new UploadConfigResolver())->resolve(
            $definition,
        );
    }

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
                allowedExtensions: ['png'],
            ),
        );
        $translator = new UploadFactoryTranslatorStub();
        $fileValidator = new FileUploadValidator(
            $translator,
            new MimeTypeDetector(),
            MimeTypeCatalog::default(),
        );
        $directoryManager = new DirectoryManager();

        $filesystem = new Filesystem(
            $directoryManager,
            new FileManager(),
            new LockManager($directoryManager),
        );

        return new UploadFactory(
            config: $config,
            service: new UploadService(
                $fileValidator,
                new ImageUploadValidator($fileValidator, $translator),
                new UploadStorage(
                    $translator,
                    $filesystem,
                ),
                new GdImageProcessor(new GdCapabilities()),
                new GdImageEncoder(new GdCapabilities()),
                new FilesystemImageFileWriter($filesystem),
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
