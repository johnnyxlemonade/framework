<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Cli;

final class CliEnvironmentResolver
{
    public function resolveBasePath(string $packageRoot, ?string $workingDirectory = null): string
    {
        if ($workingDirectory === null) {
            $currentWorkingDirectory = getcwd();
            $workingDirectory = is_string($currentWorkingDirectory) ? $currentWorkingDirectory : null;
        }

        if (
            $workingDirectory !== null
            && is_dir($workingDirectory . '/app')
            && is_dir($workingDirectory . '/storage')
        ) {
            return $workingDirectory;
        }

        return $packageRoot;
    }
}
