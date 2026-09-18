<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

use InvalidArgumentException;
use LogicException;

final class TranslationResourceRegistry
{
    /**
     * @var list<string>
     */
    private array $directories = [];

    private bool $frozen = false;

    /**
     * @throws InvalidArgumentException When the resource root does not exist or is not a directory.
     * @throws LogicException When registration happens after a translator has read a catalog.
     */
    public function register(string $directory): void
    {
        if ($this->frozen) {
            throw new LogicException('Translation resources must be registered during provider registration before the translator is first used.');
        }

        $normalized = realpath($directory);

        if ($normalized === false || !is_dir($normalized)) {
            throw new InvalidArgumentException(sprintf(
                'Translation resource root "%s" must exist and be a directory.',
                $directory,
            ));
        }

        if (in_array($normalized, $this->directories, true)) {
            return;
        }

        $this->directories[] = $normalized;
    }

    /**
     * Freezes resource registration when a translator begins reading catalogs.
     *
     * Translation resources are a bootstrap-time provider contribution. Refusing
     * later registrations prevents cached locale/group catalogs from becoming
     * inconsistent within a running application.
     *
     * @internal Used by FileTranslator to enforce the bootstrap lifecycle.
     */
    public function freeze(): void
    {
        $this->frozen = true;
    }

    /**
     * Registered directories use the conventional <locale>/<group>.php layout.
     *
     * @return list<string>
     */
    public function directories(): array
    {
        return $this->directories;
    }
}
