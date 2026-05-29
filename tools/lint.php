<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$paths = [
    $basePath . DIRECTORY_SEPARATOR . 'src',
    $basePath . DIRECTORY_SEPARATOR . 'tests',
];

$failed = false;

foreach ($paths as $path) {
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
            continue;
        }

        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $command = sprintf(
            'php -l %s',
            escapeshellarg($file->getPathname()),
        );

        passthru($command, $exitCode);

        if ($exitCode !== 0) {
            $failed = true;
        }
    }
}

$binFile = $basePath . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'lemonade';

if (is_file($binFile)) {
    passthru(sprintf('php -l %s', escapeshellarg($binFile)), $exitCode);

    if ($exitCode !== 0) {
        $failed = true;
    }
}

exit($failed ? 1 : 0);
