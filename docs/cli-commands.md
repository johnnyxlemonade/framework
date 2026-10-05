# CLI Commands

Commands implement `CommandInterface` and are registered through `app/Config/Commands.yaml`.

A command receives CLI arguments and returns an integer exit code.

## Command configuration

```yaml
module: commands
config:
  commands:
    - name: products:import
      class: App\Console\ImportProductsCommand
      description: Import products from the configured source.
```

The YAML file is mapped to `CommandsConfigDefinition`, then resolved through the existing typed config pipeline into runtime `CommandsConfig`. The definition supplies list/help metadata without constructing the command. The command class is resolved from the container only when that command is run.

Optional `aliases` may be added as a list of alternative command names. Command names and aliases must be unique.

The former class-string-only entry remains supported as a legacy compatibility form:

```yaml
commands:
  - App\Console\ImportProductsCommand
```

It requires command instantiation during registration to read `name()` and `description()`. Use the definition mapping for lazy registration.

## Command scope

When `CliKernel` runs a known command, it creates an isolated `Command` scope. The command class is resolved from that scope and can inject `CommandContext`, `CommandInput` and `CommandOutput`. `CommandContext` contains the command name, complete argv and command arguments; `CommandInput` provides small positional argument helpers; `CommandOutput` writes to the kernel's configured stdout and stderr streams.

`CommandInterface::run(array $args): int` remains unchanged. Direct calls to `run()` remain supported but do not create a framework command scope. List, help and unknown-command handling do not create a command scope or instantiate a command.

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
vendor/bin/lemonade upload:chunks:cleanup
```

`queue:install` creates the configured database-transport tables. `queue:work` requires an
asynchronous transport such as `database`; its optional arguments are queue name, transport, maximum
processed job count and idle sleep in milliseconds. These commands are suitable for supervisor or
cron-driven operations, but the framework does not provide a separate scheduler or workflow engine.

`upload:chunks:cleanup` removes expired filesystem-backed temporary chunk uploads and reports the
number of released sessions and bytes. Run it periodically when applications expose sequential chunk
upload endpoints.

See [Database](database.md), [Infrastructure modules](infrastructure.md) and
[Discovery](discovery.md), and [Uploads](uploads.md) for their configuration and operational contracts.
