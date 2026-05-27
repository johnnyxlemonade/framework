<?php

declare(strict_types=1);

namespace Lemonade\Framework\Localization;

interface TranslatorInterface
{
    public function setLocale(?string $locale): self;

    public function locale(): ?string;

    /**
     * @param array<string, scalar|null> $replacements
     */
    public function get(string $key, array $replacements = [], ?string $locale = null): string;

    /**
     * @return array<string, string>
     */
    public function group(string $group, ?string $locale = null): array;

    /**
     * @return array<string, array<string, string>>
     */
    public function all(?string $locale = null): array;
}
