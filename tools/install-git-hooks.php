<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$hooksSourceDir = $projectRoot . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'git-hooks';
$gitPath = $projectRoot . DIRECTORY_SEPARATOR . '.git';

if (!is_dir($hooksSourceDir)) {
    fwrite(STDERR, "Git hooks source directory not found: {$hooksSourceDir}" . PHP_EOL);
    exit(1);
}

$gitDir = null;

if (is_dir($gitPath)) {
    $gitDir = $gitPath;
} elseif (is_file($gitPath)) {
    $gitFileContents = trim((string) file_get_contents($gitPath));

    if (preg_match('/^gitdir:\s*(.+)$/i', $gitFileContents, $matches) !== 1) {
        fwrite(STDERR, "Unsupported .git file format: {$gitPath}" . PHP_EOL);
        exit(1);
    }

    $resolvedGitDir = $matches[1];

    if (!preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $resolvedGitDir)) {
        $resolvedGitDir = $projectRoot . DIRECTORY_SEPARATOR . $resolvedGitDir;
    }

    $gitDir = realpath($resolvedGitDir) ?: $resolvedGitDir;
}

if ($gitDir === null || !is_dir($gitDir)) {
    fwrite(STDERR, "Git directory not found for project root: {$projectRoot}" . PHP_EOL);
    exit(1);
}

if (!is_writable($gitDir)) {
    fwrite(STDERR, "Git directory is not writable: {$gitDir}" . PHP_EOL);
    exit(1);
}

$hooksTargetDir = $gitDir . DIRECTORY_SEPARATOR . 'hooks';

if (!is_dir($hooksTargetDir) && !mkdir($hooksTargetDir, 0777, true) && !is_dir($hooksTargetDir)) {
    fwrite(STDERR, "Unable to create Git hooks directory: {$hooksTargetDir}" . PHP_EOL);
    exit(1);
}

$hookFiles = [
    'pre-commit',
    'pre-push',
];

foreach ($hookFiles as $hookFile) {
    $source = $hooksSourceDir . DIRECTORY_SEPARATOR . $hookFile;
    $target = $hooksTargetDir . DIRECTORY_SEPARATOR . $hookFile;

    if (!is_file($source)) {
        fwrite(STDERR, "Hook file not found: {$source}" . PHP_EOL);
        exit(1);
    }

    if (!copy($source, $target)) {
        fwrite(STDERR, "Unable to install hook: {$hookFile}" . PHP_EOL);
        exit(1);
    }

    @chmod($target, 0755);

    echo "Installed {$hookFile} -> {$target}" . PHP_EOL;
}

exit(0);
