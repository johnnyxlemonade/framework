<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Queue\Cli;

use PHPUnit\Framework\TestCase;

final class QueueWorkCommandTest extends TestCase
{
    public function testRunPrintsWaitingLineBeforeProcessedLine(): void
    {
        $result = $this->runQueueWorkScript();

        self::assertSame(0, $result['exitCode']);
        self::assertSame('', $result['stderr']);
        self::assertSame(
            "[queue] waiting queue=default transport=database max=1 sleep=500ms\n"
            . "[queue] processed=1 queue=default\n",
            $this->normalizeLineEndings($result['stdout']),
        );
    }

    public function testDescriptionMentionsAsyncTransport(): void
    {
        $result = $this->runQueueWorkScript('echo $command->description(), PHP_EOL;');

        self::assertSame(0, $result['exitCode']);
        self::assertSame("Process queued jobs from an async transport.\n", $this->normalizeLineEndings($result['stdout']));
    }

    /**
     * @return array{exitCode:int, stdout:string, stderr:string}
     */
    private function runQueueWorkScript(string $commandCode = '$exit = $command->run(["default", "database", "1"]); exit($exit);'): array
    {
        $autoload = $this->autoloadPath();
        $script = tempnam(sys_get_temp_dir(), 'lemonade-queue-work-');
        if (!is_string($script)) {
            throw new \RuntimeException('Unable to create temporary script.');
        }

        $scriptPath = $script . '.php';
        rename($script, $scriptPath);

        file_put_contents($scriptPath, sprintf(<<<'PHP'
<?php

declare(strict_types=1);

require %s;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Queue\Cli\QueueWorkCommand;
use Lemonade\Framework\Queue\QueueBusInterface;

$queue = new class implements QueueBusInterface {
    private int $calls = 0;

    public function dispatch(object $message, ?string $transport = null, string $queue = 'default', int $delaySeconds = 0): void
    {
        unset($message, $transport, $queue, $delaySeconds);
    }

    public function addHandler(string $messageClass, callable|string $handler): void
    {
        unset($messageClass, $handler);
    }

    public function processNext(string $queue = 'default', ?string $transport = null): bool
    {
        unset($queue, $transport);
        $this->calls++;

        return $this->calls === 1;
    }
};

$command = new QueueWorkCommand($queue, new Config());
%s
PHP, var_export($autoload, true), $commandCode));

        try {
            $descriptorSpec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open([PHP_BINARY, $scriptPath], $descriptorSpec, $pipes);
            if (!is_resource($process)) {
                throw new \RuntimeException('Unable to start PHP process.');
            }

            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            return [
                'exitCode' => $exitCode,
                'stdout' => is_string($stdout) ? $stdout : '',
                'stderr' => is_string($stderr) ? $stderr : '',
            ];
        } finally {
            @unlink($scriptPath);
        }
    }

    private function autoloadPath(): string
    {
        $packageAutoload = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (is_file($packageAutoload)) {
            return $packageAutoload;
        }

        $rootAutoload = dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (is_file($rootAutoload)) {
            return $rootAutoload;
        }

        throw new \RuntimeException('Unable to locate Composer autoload file.');
    }

    private function normalizeLineEndings(string $value): string
    {
        return str_replace("\r\n", "\n", $value);
    }
}
