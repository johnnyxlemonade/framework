# CLI Commands

Commands implement `CommandInterface` and are registered through `app/Config/Commands.php`.

A command receives CLI arguments and returns an integer exit code.

## Command configuration

```php
<?php

declare(strict_types=1);

use App\Console\ImportProductsCommand;
use Lemonade\Framework\Cli\Config\CommandsConfigDefinition;

return CommandsConfigDefinition::create()
    ->commands([
        ImportProductsCommand::class,
    ]);
```

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
