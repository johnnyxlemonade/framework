<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Upload;

use InvalidArgumentException;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Upload\Config\UploadConfigDefinition;
use Lemonade\Framework\Upload\UploadPathPolicy;
use Lemonade\Framework\Upload\UploadServiceProvider;
use Lemonade\Framework\Upload\UploadVisibility;
use LogicException;
use PHPUnit\Framework\TestCase;

final class UploadPathPolicyTest extends TestCase
{
    private string $basePath = '';

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/lemonade-upload-path-' . bin2hex(random_bytes(8));
        mkdir($this->basePath . '/public/uploads', 0755, true);
        mkdir($this->basePath . '/storage/writable/uploads/files', 0700, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->basePath)) {
            (new DirectoryManager())->delete($this->basePath);
        }
    }

    public function testSeparatesPublicAndPrivateTargets(): void
    {
        $policy = $this->policy($this->basePath . '/public');

        $policy->assertStorageRootsSeparated();

        self::assertSame(
            $this->basePath . '/public/uploads/images',
            $policy->targetDirectory(UploadVisibility::Public, 'images'),
        );
        self::assertSame(
            $this->basePath . '/storage/writable/uploads/files/documents',
            $policy->targetDirectory(UploadVisibility::Private, 'documents'),
        );
        self::assertSame(
            'uploads/files/documents',
            $policy->storedRelativeDirectory(UploadVisibility::Private, 'documents'),
        );
        self::assertSame(
            $this->basePath . '/storage/writable/uploads/chunks',
            $policy->chunkRoot(),
        );
    }

    public function testRejectsPrivateRootInsidePublicWebRoot(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Private upload storage must be outside the public web root.');

        $this->policy($this->basePath)->assertStorageRootsSeparated();
    }

    public function testRejectsStagingRootInsidePublicWebRoot(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Upload staging must be outside the public web root.');

        $this->policy($this->basePath)->assertStagingRootSeparated();
    }

    public function testRejectsChunkRootInsidePublicWebRoot(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Chunk upload storage must be outside the public web root.');

        $this->policy($this->basePath)->assertChunkRootSeparated();
    }

    public function testProviderBootRejectsOverlapWhenPrivateProfileIsConfigured(): void
    {
        $container = new Container();
        $registry = new ConfigDefinitionRegistry();
        $registry->addDefinition(UploadConfigDefinition::create()->fileProfile(
            profile: 'private',
            targetDirectory: 'documents',
            maxBytes: 1024,
            allowedExtensions: ['txt'],
            visibility: UploadVisibility::Private,
        ));
        $container->singleton(ConfigDefinitionRegistry::class, $registry);
        $container->singleton(ApplicationContext::class, new ApplicationContext(
            Environment::Testing,
            new Path(
                $this->basePath,
                $this->basePath . '/storage/writable/uploads/files',
            ),
            DebugMode::disabled(),
        ));
        $provider = new UploadServiceProvider();
        $provider->register($container);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Private upload storage must be outside the public web root.');

        $provider->boot($container);
    }

    public function testRejectsRuntimeTargetOutsideVisibilityRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('escapes its configured storage root');

        $this->policy($this->basePath . '/public')->assertTargetDirectory(
            $this->basePath . '/outside',
            UploadVisibility::Public,
        );
    }

    public function testRejectsRuntimeTraversalEvenWhenItWouldNormalizeInsideRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->policy($this->basePath . '/public')->assertTargetDirectory(
            $this->basePath . '/public/uploads/safe/../nested',
            UploadVisibility::Public,
        );
    }

    public function testRejectsSymbolicLinkComponentEvenWhenItPointsInsideRoot(): void
    {
        if (DIRECTORY_SEPARATOR === '\\' || !function_exists('symlink')) {
            self::markTestSkipped('Symbolic-link containment requires a Unix-like test filesystem.');
        }

        $root = $this->basePath . '/public/uploads';
        mkdir($root . '/real', 0755);

        if (!symlink($root . '/real', $root . '/linked')) {
            self::markTestSkipped('The test filesystem does not permit symbolic links.');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot contain symbolic links');

        $this->policy($this->basePath . '/public')->assertTargetDirectory(
            $root . '/linked/nested',
            UploadVisibility::Public,
        );
    }

    private function policy(string $publicPath): UploadPathPolicy
    {
        return new UploadPathPolicy(new ApplicationContext(
            Environment::Testing,
            new Path($this->basePath, $publicPath),
            DebugMode::disabled(),
        ));
    }
}
