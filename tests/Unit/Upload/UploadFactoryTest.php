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
use Lemonade\Framework\Mime\MimeRisk;
use Lemonade\Framework\Upload\Config\UploadConfigDefinition;
use Lemonade\Framework\Upload\Config\UploadConfigResolver;
use Lemonade\Framework\Upload\Exception\UploadValidationException;
use Lemonade\Framework\Upload\FileUploadOptions;
use Lemonade\Framework\Upload\FileUploadValidator;
use Lemonade\Framework\Image\Gd\GdCapabilities;
use Lemonade\Framework\Image\Gd\GdImageEncoder;
use Lemonade\Framework\Image\Gd\GdImageProcessor;
use Lemonade\Framework\Upload\ImageUploadValidator;
use Lemonade\Framework\Mime\MimeTypeCatalog;
use Lemonade\Framework\Mime\MimeTypeDetector;
use Lemonade\Framework\Upload\Staging\UploadStaging;
use Lemonade\Framework\Upload\Storage\UploadStorage;
use Lemonade\Framework\Upload\UploadFactory;
use Lemonade\Framework\Upload\UploadPathPolicy;
use Lemonade\Framework\Upload\UploadService;
use Lemonade\Framework\Upload\UploadVisibility;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\TestCase;

final class UploadFactoryTest extends TestCase
{
    public function testDefaultProfilesUseCatalogBackedExtensionPolicies(): void
    {
        $definition = require dirname(__DIR__, 3) . '/src/Config/Upload.php';

        self::assertInstanceOf(UploadConfigDefinition::class, $definition);

        $config = (new UploadConfigResolver())->resolve($definition);

        self::assertSame(['pdf', 'doc', 'docx', 'txt'], $config->files['default']->allowedExtensions());
        self::assertSame(['jpg', 'jpeg', 'png', 'webp'], $config->images['default']->allowedExtensions());
        self::assertSame(UploadVisibility::Public, $config->files['default']->visibility());
        self::assertSame(UploadVisibility::Public, $config->images['default']->visibility());
        self::assertSame([], $config->files['default']->allowedRisks());
        self::assertSame([], $config->images['default']->allowedRisks());
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

    public function testPrivateFileOptionsResolveBelowWritableStorageWithoutPublicPath(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path('/var/www/framework', '/var/www/framework/public'),
            DebugMode::disabled(),
        );
        $definition = UploadConfigDefinition::create()->fileProfile(
            profile: 'private',
            targetDirectory: 'documents',
            maxBytes: 1024,
            allowedExtensions: ['txt'],
            visibility: UploadVisibility::Private,
        );
        $factory = $this->factory($context, $definition);

        $options = $factory->fileOptions('private');

        self::assertSame(UploadVisibility::Private, $options->visibility());
        self::assertSame(
            '/var/www/framework/storage/writable/uploads/files/documents',
            $options->targetDirectory(),
        );
        self::assertSame('uploads/files/documents', $options->targetRelativeDirectory());
    }

    public function testFileWithOptionsEnforcesPublicRiskPolicy(): void
    {
        $basePath = sys_get_temp_dir() . '/lemonade-factory-risk-' . bin2hex(random_bytes(8));
        mkdir($basePath . '/public', 0700, true);
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($basePath, $basePath . '/public'),
            DebugMode::disabled(),
        );
        $factory = $this->factory($context);
        $path = tempnam(sys_get_temp_dir(), 'lemonade-public-html-');

        if ($path === false) {
            self::fail('Temporary upload file cannot be created.');
        }

        file_put_contents($path, '<!doctype html><script>alert(1)</script>');
        $size = filesize($path);

        if (!is_int($size)) {
            self::fail('Temporary upload size cannot be read.');
        }

        try {
            $uploader = $factory->fileWithOptions(new FileUploadOptions(
                targetDirectory: $basePath . '/public/uploads/pages',
                targetRelativeDirectory: 'uploads/pages',
                allowedExtensions: ['html'],
                allowedRisks: [MimeRisk::ActiveContent],
            ));

            $this->expectException(UploadValidationException::class);
            $uploader->upload(new UploadedFile(
                $path,
                $size,
                UPLOAD_ERR_OK,
                'page.html',
                'text/html',
            ));
        } finally {
            @unlink($path);

            if (is_dir($basePath)) {
                (new DirectoryManager())->delete($basePath);
            }
        }
    }

    private function factory(
        ApplicationContext $context,
        ?UploadConfigDefinition $definition = null,
    ): UploadFactory {
        $definition ??= UploadConfigDefinition::create()->fileProfile(
                profile: 'default',
                targetDirectory: 'images',
                maxBytes: 1024,
                allowedExtensions: ['png'],
        );
        $config = (new UploadConfigResolver())->resolve($definition);
        $translator = new UploadFactoryTranslatorStub();
        $fileValidator = new FileUploadValidator(
            $translator,
            new MimeTypeDetector(),
            MimeTypeCatalog::default(),
        );
        $directoryManager = new DirectoryManager();
        $imageProcessor = new GdImageProcessor(new GdCapabilities());

        $filesystem = new Filesystem(
            $directoryManager,
            new FileManager(),
            new LockManager($directoryManager),
        );
        $pathPolicy = new UploadPathPolicy($context);

        return new UploadFactory(
            config: $config,
            service: new UploadService(
                $fileValidator,
                new ImageUploadValidator($fileValidator, $translator, $imageProcessor),
                new UploadStorage(
                    $translator,
                    $filesystem,
                    $pathPolicy,
                ),
                new GdImageEncoder(new GdCapabilities()),
                new UploadStaging($translator, $filesystem, $pathPolicy),
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
