<?php

declare(strict_types=1);

namespace Lemonade\Framework\Discovery\Console;

use Lemonade\Framework\Cli\CommandInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Discovery\Sitemap\SitemapFileGenerator;
use RuntimeException;

use function fclose;
use function flock;
use function fopen;
use function fwrite;
use function is_resource;

use const LOCK_EX;
use const LOCK_NB;
use const LOCK_UN;

final class GenerateSitemapCommand implements CommandInterface
{
    /** @var resource */
    private $stdout;
    /** @var resource */
    private $stderr;

    public function __construct(
        private readonly SitemapFileGenerator $fileGenerator,
        private readonly Config $config,
        private readonly ApplicationContext $context,
        mixed $stdout = null,
        mixed $stderr = null,
    ) {
        if ($stdout !== null && !is_resource($stdout)) {
            throw new RuntimeException('GenerateSitemapCommand stdout must be a valid resource.');
        }
        if ($stderr !== null && !is_resource($stderr)) {
            throw new RuntimeException('GenerateSitemapCommand stderr must be a valid resource.');
        }

        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
    }

    public function name(): string
    {
        return 'discovery:sitemap:generate';
    }

    public function description(): string
    {
        return 'Generates cached sitemap files.';
    }

    public function run(array $args): int
    {
        unset($args);
        $start = microtime(true);
        $cliOutput = $this->config->bool('discovery.sitemap.cli_output', true);
        $relativePath = $this->config->string('discovery.sitemap.cache_path', 'storage/cache/discovery') ?? 'storage/cache/discovery';
        $cachePath = $this->context->basePath() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0775, true);
        }

        $lockFile = $cachePath . DIRECTORY_SEPARATOR . '.sitemap.lock';
        $handle = fopen($lockFile, 'c+');
        if (!is_resource($handle)) {
            throw new RuntimeException('Unable to open sitemap lock file.');
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            if ($cliOutput) {
                $this->writeStderr("Sitemap generation already running.\n");
            }
            fclose($handle);
            return 1;
        }

        try {
            $result = $this->fileGenerator->generate();
            $elapsed = (int) round((microtime(true) - $start) * 1000);
            if ($cliOutput) {
                $this->writeStdout(sprintf(
                    "URLs: %d\nFiles: %d\nOutput: %s\nGzip: %s\nDuration: %dms\n",
                    $result->urlCount(),
                    count($result->files()),
                    $result->outputPath(),
                    $result->gzip() ? 'yes' : 'no',
                    $elapsed,
                ));
            }

            return 0;
        } catch (\Throwable $exception) {
            if ($cliOutput) {
                $this->writeStderr('Failed: ' . $exception->getMessage() . "\n");
            }
            return 1;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function writeStdout(string $message): void
    {
        fwrite($this->stdout, $message);
    }

    private function writeStderr(string $message): void
    {
        fwrite($this->stderr, $message);
    }
}
