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
use LogicException;
use PHPUnit\Framework\TestCase;

final class UploadPathPolicyTest extends TestCase
{
    private string $basePath = '';

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/lemonade-upload-path-' . bin2hex(random_bytes(8));
        mkdir($this->basePath . '/public', 0755, true);
        mkdir($this->basePath . '/storage/uploads', 0700, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->basePath)) {
            (new DirectoryManager())->delete($this->basePath);
        }
    }

    public function testResolvesPrivateTargets(): void
    {
        $policy = $this->policy($this->basePath . '/public');

        self::assertSame(
            $this->basePath . '/storage/uploads/images',
            $policy->targetDirectory('images'),
        );
        self::assertSame(
            $this->basePath . '/storage/uploads/documents',
            $policy->targetDirectory('documents'),
        );
        self::assertSame(
            'uploads/documents',
            $policy->storedRelativeDirectory('documents'),
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

        $this->policy($this->basePath);
    }

    public function testRejectsStagingRootInsidePublicWebRoot(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Upload staging must be outside the public web root.');

        $this->policy($this->basePath . '/storage/writable/uploads/staging');
    }

    public function testRejectsChunkRootInsidePublicWebRoot(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Chunk upload storage must be outside the public web root.');

        $this->policy($this->basePath . '/storage/writable/uploads/chunks');
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
        ));
        $container->singleton(ConfigDefinitionRegistry::class, $registry);
        $container->singleton(ApplicationContext::class, new ApplicationContext(
            Environment::Testing,
            new Path(
                $this->basePath,
                $this->basePath . '/storage/uploads',
            ),
            DebugMode::disabled(),
        ));
        $provider = new UploadServiceProvider();
        $provider->register($container);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Private upload storage must be outside the public web root.');

        $provider->boot($container);
    }

    public function testRejectsRuntimeTargetOutsideUploadRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('escapes its configured storage root');

        $this->policy($this->basePath . '/public')->assertTargetDirectory(
            $this->basePath . '/outside',
        );
    }

    public function testRejectsRuntimeTraversalEvenWhenItWouldNormalizeInsideRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->policy($this->basePath . '/public')->assertTargetDirectory(
            $this->basePath . '/storage/uploads/safe/../nested',
        );
    }

    public function testRejectsSymbolicLinkComponentEvenWhenItPointsInsideRoot(): void
    {
        if (DIRECTORY_SEPARATOR === '\\' || !function_exists('symlink')) {
            self::markTestSkipped('Symbolic-link containment requires a Unix-like test filesystem.');
        }

        $root = $this->basePath . '/storage/uploads';
        mkdir($root . '/real', 0755);

        if (!symlink($root . '/real', $root . '/linked')) {
            self::markTestSkipped('The test filesystem does not permit symbolic links.');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot contain symbolic links');

        $this->policy($this->basePath . '/public')->assertTargetDirectory(
            $root . '/linked/nested',
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
