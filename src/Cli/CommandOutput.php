<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

final class CommandOutput
{
    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    public function __construct(mixed $stdout, mixed $stderr)
    {
        if (!is_resource($stdout) || !is_resource($stderr)) {
            throw new \InvalidArgumentException('CommandOutput stdout and stderr must be valid resources.');
        }

        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }

    public function write(string $message): void
    {
        fwrite($this->stdout, $message);
    }

    public function writeln(string $message = ''): void
    {
        $this->write($message . PHP_EOL);
    }

    public function error(string $message): void
    {
        fwrite($this->stderr, $message);
    }

    public function errorln(string $message = ''): void
    {
        $this->error($message . PHP_EOL);
    }
}
