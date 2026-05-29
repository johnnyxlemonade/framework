# CLI Commands

Commands implement `CommandInterface` and are registered through `app/Config/Commands.php`.

A command receives CLI arguments and returns an integer exit code.

## Command configuration

```php
<?php

use App\Console\ImportProductsCommand;

return [
    'commands' => [
        ImportProductsCommand::class,
    ],
];
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
php bin/lemonade
php bin/lemonade list
php bin/lemonade products:import
```

When no command is provided, the CLI kernel defaults to the command list.
