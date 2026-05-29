# CLI Flow

CLI execution uses the same application context, container, configuration system and provider model as HTTP execution.

This makes console commands useful for cron jobs, imports, exports and backend integrations because command classes can depend on the same configured services as controllers.

## Flow

```text
bin/lemonade
-> ApplicationContextFactory::fromGlobals()
-> KernelFactory / CliKernel wiring
-> CliKernel::handle($argv)
   -> start benchmark run with entrypoint=cli
-> CliKernel::bootstrap()
   -> load conventional config files, including Commands.php
   -> apply runtime app config
   -> register core providers
   -> register common framework providers
   -> register ConsoleServiceProvider
   -> register application providers
-> build CommandRegistry
   -> read configured command classes from config key "commands"
   -> validate command classes
   -> register commands
-> resolve command name from argv
   -> default to "list" when no command is provided
   -> print command list for list, --help or -h
   -> return 1 for unknown commands
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

Commands are configured in `app/Config/Commands.php`.

```php
<?php

use App\Console\ImportProductsCommand;

return [
    'commands' => [
        ImportProductsCommand::class,
    ],
];
```

## Running commands

```bash
php bin/lemonade
php bin/lemonade list
php bin/lemonade products:import
```
