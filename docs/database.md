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

## Fixed-window rate limiting

`DatabaseRateLimiter` is a generic `RateLimiterInterface` implementation for
MySQL and MariaDB. It atomically stores HMAC-addressed opaque buckets in a small
application-owned table. The framework supplies the limiter mechanism; the host
application owns the migration that creates the table and any feature-specific
policy or key semantics.

The limiter provides `status()`, `hit()`, and `clear()`. `status()` is read-only,
while `hit()` atomically records one attempt using a fixed window. The database
table requires a binary `key_hash` primary key, `scope` column, integer
`attempts`, and `expires_at` columns. Every `status()` and `hit()` call requires
a scope as diagnostic metadata. It must be a short identifier containing only
letters, numbers, `.`, `_`, `-`, or `:` and must never contain a limiter key or a
sensitive identifier. It does not affect the HMAC bucket identity or rate-limit
decision. `hit()` replaces prior metadata for the same bucket. Expired buckets
are reset by a subsequent `hit()` and may be cleaned up opportunistically by the
host application.

## Container-managed database services and models

The default application container resolves `ConnectionInterface`, `DatabaseDriverInterface`, and
`Database` as one shared connection stack. A concrete `Model` resolved with the container-managed
`DatabaseDriverInterface` therefore participates safely in `Database::transaction(...)` together
with `Database` and Query Builder writes.

`DatabaseFactory::create()` is intentionally different: it explicitly creates an independent
`Database` instance with its own connection and driver for callers that need a separate database
context.

## Query Builder SQL boundaries

`QueryBuilder` separates structured clauses from explicitly raw SQL. Structured table references
accept a table or schema-qualified table plus one optional alias, for example `users AS u` or
`reporting.users u`. Column inputs accept bare or qualified identifiers. They are validated before
the active driver quotes them; SQL expressions, comments, delimiters and statement fragments are
rejected rather than passed through the driver's more permissive legacy identifier helper.

The preferred simple JOIN API compares two validated column identifiers:

```php
$rows = $database->table('users AS u')
    ->joinOn('profiles AS p', 'u.id', '=', 'p.user_id', 'LEFT')
    ->where('p.enabled', true)
    ->getArray();
```

JOIN types are case-insensitively normalized and limited to `INNER`, `LEFT`, `LEFT OUTER`, `RIGHT`,
`RIGHT OUTER`, `FULL`, `FULL OUTER`, and `CROSS`. An empty or unknown type is an error; there is no
fallback to `INNER`. The selected database dialect must still support the chosen standard JOIN form.
Structured JOIN comparison operators are limited to `=`, `!=`, `<>`, `<`, `<=`, `>`, and `>=`.

Complex ON expressions remain an explicit trusted-raw boundary. Their SQL structure must be owned by
application code, while every untrusted value uses a placeholder and binding:

```php
$rows = $database->table('users AS u')
    ->joinRaw(
        table: 'profiles AS p',
        condition: 'p.user_id = u.id AND p.state = ?',
        bindings: [$requestedState],
        type: 'LEFT',
    )
    ->getArray();
```

The historical `join($table, $condition, $type)` signature is retained for compatibility and treats
`$condition` as trusted raw SQL. It now validates the table and JOIN type and accepts an optional
fourth bindings argument. New code should use `joinOn()` for structured comparisons or `joinRaw()`
to make the trust boundary visible. `joinSubqueryOn()` and `joinSubqueryRaw()` provide the same split
for compiled subqueries; subquery bindings always precede ON-condition bindings in placeholder order.
The builder deliberately does not attempt to parse or rewrite arbitrary ON expressions.

`fromRaw()`, `selectRaw()`, `whereRaw()`, and `havingRaw()` are the other explicit raw SQL entry
points. Their SQL fragments must never be derived from untrusted input. Their binding arrays are the
supported path for values. Structured `where`, `having`, membership, range, ordering, grouping,
insert, update and batch operations validate their identifiers and bind their values.

## Migrations

Lemonade provides a small, one-way migration runner. Concrete migration classes stay in the
application and are registered explicitly from an application service provider.

```php
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use App\Database\Migrations\CreateUsersTable;

final class AppMigrationProvider implements BootableServiceProviderInterface
{
    public function boot(ContainerInterface $container): void
    {
        $container->get(MigrationRegistry::class)->register(CreateUsersTable::class);
    }
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
