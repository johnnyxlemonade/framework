# CLI Flow

CLI execution uses the same application context, container, configuration system and provider model as HTTP execution.

This makes console commands useful for cron jobs, imports, exports and backend integrations because command classes can depend on the same configured services as controllers.

## Flow

```text
bin/lemonade
-> CliEnvironmentResolver::resolveBasePath()
-> ApplicationContextFactory::fromGlobals()
-> CliKernelFactory::create()
-> CliKernel::handle($argv)
-> CliKernel::bootstrap()
   -> load the manifest-selected shared and CLI config definitions
   -> apply runtime app config
   -> register core providers
   -> register ConsoleServiceProvider
   -> register common framework providers
   -> register application providers
-> build CommandRegistry
   -> validate configured CommandDefinition metadata and command classes
   -> register command definitions without constructing commands
-> resolve command name from argv
   -> default to "list" when no command is provided
   -> print command list from definition metadata for list, --help or -h
   -> return 1 for unknown commands
-> begin isolated Command scope for the selected command
   -> bind CommandContext, CommandInput and CommandOutput
   -> resolve only the selected command class from that scope
-> CommandInterface::run($args)
-> integer exit code
```

## Command example

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
        // Import products here.

        return 0;
    }
}
```

## Configuration

Commands are configured in `app/Config/Commands.yaml`.

```yaml
module: commands
config:
  commands:
    - name: products:import
      class: App\Console\ImportProductsCommand
      description: Import products from the configured source.
```

Internally this YAML payload is still mapped to `CommandsConfigDefinition` and resolved into runtime `CommandsConfig`.

CLI does not create HTTP diagnostics or automatic SQL capture. Command scopes still close in
`finally`, including when command execution throws.

The legacy class-string entry is still accepted, but it constructs the command to obtain its metadata. Prefer the definition mapping so list and help remain lazy. When `CliKernel` runs a known command, it creates an isolated `Command` scope, binds `CommandContext`, `CommandInput` and `CommandOutput`, and closes the scope in `finally`. List, help and unknown-command handling do not create a command scope; direct calls to `CommandInterface::run()` outside `CliKernel` do not create one either.

`CommandRegistry::get()` is a legacy lookup API outside this dispatch flow. New command execution
should go through `CliKernel`; list/help use `CommandDefinition` metadata and do not instantiate a
command.

## Running commands

```bash
vendor/bin/lemonade
vendor/bin/lemonade list
vendor/bin/lemonade products:import
```
