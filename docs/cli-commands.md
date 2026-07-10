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
