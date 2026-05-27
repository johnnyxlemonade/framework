<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta;

use Lemonade\Framework\Core\Config;

final class MetaComponent
{
    public function __construct(
        private readonly Config $config,
    ) {}

    public function make(MetaData $data): MetaFactory
    {
        return new MetaFactory($this->applyDefaults($data));
    }

    public function render(MetaData|MetaFactory $meta): string
    {
        if ($meta instanceof MetaFactory) {
            return $meta->toHtml();
        }

        return (new MetaFactory($this->applyDefaults($meta)))->toHtml();
    }

    private function applyDefaults(MetaData $data): MetaData
    {
        $websiteName = $this->config->string('meta.website_name', 'website') ?? 'website';
        $charset = $this->config->string('meta.charset', 'UTF-8') ?? 'UTF-8';
        $viewport = $this->config->string('meta.viewport', 'width=device-width, initial-scale=1') ?? 'width=device-width, initial-scale=1';
        $rating = $this->config->string('meta.rating', 'General') ?? 'General';
        $titleSeparator = $this->config->string('meta.title_separator', ' - ') ?? ' - ';

        return $data->withDefaults(
            websiteName: $websiteName,
            charset: $charset,
            viewport: $viewport,
            rating: $rating,
            titleSeparator: $titleSeparator,
        );
    }
}
