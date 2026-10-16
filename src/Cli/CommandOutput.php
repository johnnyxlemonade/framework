<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

/**
 * Writes command output to separate standard-output and standard-error streams.
 */
final class CommandOutput
{
    /**
     * @var resource
     */
    private $stdout;

    /**
     * @var resource
     */
    private $stderr;

    /**
     * Creates output backed by writable stream resources.
     *
     * @param resource $stdout Standard-output stream.
     * @param resource $stderr Standard-error stream.
     *
     * @throws \InvalidArgumentException When either value is not a stream resource.
     */
    public function __construct(mixed $stdout, mixed $stderr)
    {
        if (!is_resource($stdout) || !is_resource($stderr)) {
            throw new \InvalidArgumentException('CommandOutput stdout and stderr must be valid resources.');
        }

        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }

    /**
     * Writes a message to standard output without adding a line ending.
     */
    public function write(string $message): void
    {
        fwrite($this->stdout, $message);
    }

    /**
     * Writes a message followed by the platform line ending to standard output.
     */
    public function writeln(string $message = ''): void
    {
        $this->write($message . PHP_EOL);
    }

    /**
     * Writes a message to standard error without adding a line ending.
     */
    public function error(string $message): void
    {
        fwrite($this->stderr, $message);
    }

    /**
     * Writes a message followed by the platform line ending to standard error.
     */
    public function errorln(string $message = ''): void
    {
        $this->error($message . PHP_EOL);
    }
}
