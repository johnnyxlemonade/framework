# CLI Commands

Commands implement `CommandInterface` and are registered through `app/Config/Commands.yaml`.

A command receives CLI arguments and returns an integer exit code.

## Command configuration

```yaml
module: commands
config:
  commands:
    - App\Console\ImportProductsCommand
```

The YAML file is mapped to `CommandsConfigDefinition`, then resolved through the existing typed config pipeline into runtime `CommandsConfig`.

## Command class

```php
<?php

namespace App\Console;

use Lemonade\Framework\Cli\CommandInterface;

final class ImportProductsCommand implements CommandInterface
{
    public function name(): string
    {
        return 'products:import';
    }

    public function description(): string
    {
        return 'Import products from the configured source.';
    }

    /**
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        // ...

        return 0;
    }
}
```

## Running commands

```bash
vendor/bin/lemonade
vendor/bin/lemonade list
vendor/bin/lemonade products:import
```

When no command is provided, the CLI kernel defaults to the command list.

## Framework operational commands

Framework providers can register operational commands alongside application commands. The exact
set depends on enabled configuration and services. Common package commands include:

```bash
vendor/bin/lemonade database:migrate
vendor/bin/lemonade database:migrate:status
vendor/bin/lemonade queue:install
vendor/bin/lemonade queue:work
vendor/bin/lemonade discovery:sitemap:generate
```

`queue:install` creates the configured database-transport tables. `queue:work` requires an
asynchronous transport such as `database`; its optional arguments are queue name, transport, maximum
processed job count and idle sleep in milliseconds. These commands are suitable for supervisor or
cron-driven operations, but the framework does not provide a separate scheduler or workflow engine.

See [Database](database.md), [Infrastructure modules](infrastructure.md) and
[Discovery](discovery.md) for their configuration and operational contracts.
