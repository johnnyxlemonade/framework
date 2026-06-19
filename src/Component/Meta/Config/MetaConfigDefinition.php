<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class MetaConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'meta';
    }

    public function websiteName(string $websiteName): self
    {
        return $this->set('website_name', $websiteName);
    }

    public function charset(string $charset): self
    {
        return $this->set('charset', $charset);
    }

    public function viewport(string $viewport): self
    {
        return $this->set('viewport', $viewport);
    }

    public function rating(string $rating): self
    {
        return $this->set('rating', $rating);
    }

    public function titleSeparator(string $titleSeparator): self
    {
        return $this->set('title_separator', $titleSeparator);
    }
}
