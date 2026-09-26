<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Migration;

use FilesystemIterator;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Database\Migration\Exception\MigrationDiscoveryException;
use Lemonade\Framework\Database\Migration\MigrationDirectoryRegistrar;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class MigrationDirectoryRegistrarTest extends TestCase
{
    private string $root = '';

    private string $namespace = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-migrations-' . uniqid('', true);
        mkdir($this->root, 0775, true);
        $this->namespace = 'Lemonade\\Framework\\Tests\\Fixtures\\MigrationDiscovery\\Case' . str_replace('.', '', uniqid('', true));
        $GLOBALS['lemonade_migration_discovery_order'] = [];
        spl_autoload_register([$this, 'autoloadFixture']);
    }

    protected function tearDown(): void
    {
        spl_autoload_unregister([$this, 'autoloadFixture']);
        $this->deleteDirectory($this->root);
        unset($GLOBALS['lemonade_migration_discovery_order']);
    }

    public function testRegistersMigrationsFromExplicitDirectoryAndMapsRecursiveNamespaces(): void
    {
        $this->writeMigration('ZetaMigration.php', $this->namespace, 'ZetaMigration', '20260910110000_zeta');
        $this->writeMigration('Nested/AlphaMigration.php', $this->namespace . '\\Nested', 'AlphaMigration', '20260909110000_alpha');
        $registry = new MigrationRegistry(new Container());

        (new MigrationDirectoryRegistrar())->registerDirectory($registry, $this->root, $this->namespace);

        self::assertSame([
            '20260909110000_alpha',
            '20260910110000_zeta',
        ], $registry->identifiers());
    }

    public function testProcessesFilesInDeterministicPathOrder(): void
    {
        $this->writeMigration('ZetaMigration.php', $this->namespace, 'ZetaMigration', '20260910110000_zeta', true);
        $this->writeMigration('Nested/AlphaMigration.php', $this->namespace . '\\Nested', 'AlphaMigration', '20260909110000_alpha', true);
        $registry = new MigrationRegistry(new Container());

        (new MigrationDirectoryRegistrar())->registerDirectory($registry, $this->root, $this->namespace);

        self::assertSame([
            $this->namespace . '\\Nested\\AlphaMigration',
            $this->namespace . '\\ZetaMigration',
        ], $GLOBALS['lemonade_migration_discovery_order']);
    }

    public function testDoesNotSearchOutsideTheExplicitDirectory(): void
    {
        $this->writeMigration('Included/InsideMigration.php', $this->namespace . '\\Included', 'InsideMigration', '20260910110000_inside');
        $this->writeMigration('OutsideMigration.php', $this->namespace, 'OutsideMigration', '20260910120000_outside');
        $registry = new MigrationRegistry(new Container());

        (new MigrationDirectoryRegistrar())->registerDirectory(
            $registry,
            $this->root . DIRECTORY_SEPARATOR . 'Included',
            $this->namespace . '\\Included',
        );

        self::assertSame(['20260910110000_inside'], $registry->identifiers());
    }

    public function testRejectsMissingDirectory(): void
    {
        $this->expectException(MigrationDiscoveryException::class);
        $this->expectExceptionMessage('does not exist');

        (new MigrationDirectoryRegistrar())->registerDirectory(
            new MigrationRegistry(new Container()),
            $this->root . DIRECTORY_SEPARATOR . 'missing',
            $this->namespace,
        );
    }

    public function testRejectsPathThatIsNotADirectory(): void
    {
        $file = $this->root . DIRECTORY_SEPARATOR . 'migration.php';
        file_put_contents($file, '<?php');

        $this->expectException(MigrationDiscoveryException::class);
        $this->expectExceptionMessage('is not a directory');

        (new MigrationDirectoryRegistrar())->registerDirectory(
            new MigrationRegistry(new Container()),
            $file,
            $this->namespace,
        );
    }

    public function testRejectsEmptyNamespace(): void
    {
        $this->expectException(MigrationDiscoveryException::class);
        $this->expectExceptionMessage('namespace cannot be empty');

        (new MigrationDirectoryRegistrar())->registerDirectory(new MigrationRegistry(new Container()), $this->root, ' \\ ');
    }

    public function testRejectsPhpFileWithoutExpectedClass(): void
    {
        $this->writeFile('MissingClass.php', "<?php\n\ndeclare(strict_types=1);\n");

        $this->expectException(MigrationDiscoveryException::class);
        $this->expectExceptionMessage('does not declare expected class');

        (new MigrationDirectoryRegistrar())->registerDirectory(new MigrationRegistry(new Container()), $this->root, $this->namespace);
    }

    public function testRejectsClassThatCannotBeAutoloaded(): void
    {
        $namespace = 'Unmapped\\MigrationDiscovery';
        $this->writeMigration('UnautoloadableMigration.php', $namespace, 'UnautoloadableMigration', '20260910110000_unautoloadable');

        $this->expectException(MigrationDiscoveryException::class);
        $this->expectExceptionMessage('could not be autoloaded');

        (new MigrationDirectoryRegistrar())->registerDirectory(new MigrationRegistry(new Container()), $this->root, $namespace);
    }

    public function testRejectsClassWithoutMigrationContract(): void
    {
        $this->writeFile('NotAMigration.php', "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$this->namespace};\n\nfinal class NotAMigration {}\n");

        $this->expectException(MigrationDiscoveryException::class);
        $this->expectExceptionMessage('must implement');

        (new MigrationDirectoryRegistrar())->registerDirectory(new MigrationRegistry(new Container()), $this->root, $this->namespace);
    }

    public function testDelegatesDuplicateIdentifierValidationToRegistry(): void
    {
        $this->writeMigration('FirstMigration.php', $this->namespace, 'FirstMigration', '20260910110000_duplicate');
        $this->writeMigration('SecondMigration.php', $this->namespace, 'SecondMigration', '20260910110000_duplicate');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('already registered');

        (new MigrationDirectoryRegistrar())->registerDirectory(new MigrationRegistry(new Container()), $this->root, $this->namespace);
    }

    public function testDelegatesInvalidIdentifierValidationToRegistry(): void
    {
        $this->writeMigration('InvalidIdentifierMigration.php', $this->namespace, 'InvalidIdentifierMigration', 'invalid');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must match');

        (new MigrationDirectoryRegistrar())->registerDirectory(new MigrationRegistry(new Container()), $this->root, $this->namespace);
    }

    public function autoloadFixture(string $class): void
    {
        $prefix = $this->namespace . '\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = $this->root . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }

    private function writeMigration(string $relativePath, string $namespace, string $class, string $identifier, bool $trace = false): void
    {
        $traceStatement = $trace
            ? "        \$GLOBALS['lemonade_migration_discovery_order'][] = self::class;\n"
            : '';
        $this->writeFile($relativePath, "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse Lemonade\\Framework\\Database\\Migration\\MigrationInterface;\nuse Lemonade\\Framework\\Database\\Schema\\Schema;\n\nfinal class {$class} implements MigrationInterface\n{\n    public static function identifier(): string\n    {\n{$traceStatement}        return '{$identifier}';\n    }\n\n    public function up(Schema \$schema): void\n    {\n        unset(\$schema);\n    }\n}\n");
    }

    private function writeFile(string $relativePath, string $contents): void
    {
        $file = $this->root . DIRECTORY_SEPARATOR . $relativePath;
        $directory = dirname($file);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($file, $contents);
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }

            if ($item->isDir()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
