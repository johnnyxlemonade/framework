# Database

The database layer separates connection backend from SQL/schema dialect.

The selected `driver` defines how the framework connects and executes queries. The selected or implicit `dialect` defines SQL grammar and schema behavior.

## Supported driver/provider families

- native MySQL
- ODBC
- PDO
- SQLite through PDO

## Native MySQL configuration

```yaml
module: database
config:
  default: default
  connections:
    default:
      driver: mysql
      host: 127.0.0.1
      port: 3306
      database: app
      username: root
      password: ''
```

## PDO with MySQL dialect

```yaml
module: database
config:
  default: default
  connections:
    default:
      driver: pdo
      dialect: mysql
      dsn: mysql:host=127.0.0.1;port=3306;dbname=app;charset=utf8mb4
      username: root
      password: ''
```

## PDO with SQLite dialect

```yaml
module: database
config:
  default: default
  connections:
    default:
      driver: pdo
      dialect: sqlite
      dsn: sqlite:/absolute/path/database.sqlite
```

SQLite schema support is intentionally conservative. Some `ALTER TABLE` operations are not supported and should be implemented through a dedicated rebuild-table strategy.

App-level YAML is still mapped into `DatabaseConfigDefinition` before `DatabaseConfigResolver` produces runtime config objects.

## Container-managed database services and models

The default application container resolves `ConnectionInterface`, `DatabaseDriverInterface`, and
`Database` as one shared connection stack. A concrete `Model` resolved with the container-managed
`DatabaseDriverInterface` therefore participates safely in `Database::transaction(...)` together
with `Database` and Query Builder writes.

`DatabaseFactory::create()` is intentionally different: it explicitly creates an independent
`Database` instance with its own connection and driver for callers that need a separate database
context.

## Migrations

Lemonade provides a small, one-way migration runner. Concrete migration classes stay in the
application and are registered explicitly from an application service provider.

```php
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use App\Database\Migrations\CreateUsersTable;

public function register(ContainerInterface $container): void
{
    $container->get(MigrationRegistry::class)->register(CreateUsersTable::class);
}
```

### Explicit directory discovery

For a conventional PSR-4 migration directory, use `MigrationDirectoryRegistrar`. Discovery is
explicit: the caller supplies both one filesystem directory and its namespace prefix. The registrar
recurses only below that directory; it never scans an application root, Composer metadata, or an
implicit migration location.

```php
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationDirectoryRegistrar;
use Lemonade\Framework\Database\Migration\MigrationRegistry;

final class AppMigrationProvider implements BootableServiceProviderInterface
{
    public function boot(ContainerInterface $container): void
    {
        (new MigrationDirectoryRegistrar())->registerDirectory(
            $container->get(MigrationRegistry::class),
            __DIR__ . '/../Database/Migrations',
            'App\\Database\\Migrations',
        );
    }
}
```

Recursive paths map directly to PSR-4 namespaces: for example,
`Database/Migrations/Feature/CreateTable.php` maps to
`App\Database\Migrations\Feature\CreateTable`. Files are processed in sorted path order for
deterministic diagnostics. Each derived class must autoload and implement `MigrationInterface`.

The registrar only adds class strings to `MigrationRegistry`; it does not instantiate or run
migrations, open a database connection, or register services. `MigrationRegistry` remains the
single owner of identifier validation, identifier ordering, and duplicate-identifier guards.

```php
namespace App\Database\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Schema;

final class CreateUsersTable implements MigrationInterface
{
    public static function identifier(): string
    {
        return '20260908090000_create_users';
    }

    public function up(Schema $schema): void
    {
        $schema->create('users', static function ($table): void {
            $table->id();
            $table->string('email', 191)->unique();
        });
    }
}
```

Run and inspect migrations through the framework CLI:

```bash
vendor/bin/lemonade database:migrate
vendor/bin/lemonade database:migrate:status
```

The identifier format is `YYYYMMDDHHMMSS_description`. Its timestamp prefix determines migration order, so registrations may be written in any order. Applied historical migrations must not be changed casually.

The runner records successful identifiers in the `migrations` table only after `up()` returns. Migrations are one-way: there is no automatic transaction or rollback. Available migration operations are limited by the selected Schema grammar and database dialect; SQLite and generic ODBC have more limited `ALTER TABLE` support than MySQL.

When database state is unavailable, `database:migrate:status` still lists registered migrations as `UNKNOWN` and exits with an error status; `database:migrate` cannot run without a configured connection.

Migration identifiers are explicit and registration is deliberate. Generating a new identifier may be added later as a developer-experience command, but Lemonade does not provide `make:migration` yet.
