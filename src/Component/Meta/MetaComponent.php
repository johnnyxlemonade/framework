<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta;

use Lemonade\Framework\Component\Meta\Config\MetaConfig;

final class MetaComponent
{
    public function __construct(
        private readonly MetaConfig $config,
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
        return $data->withDefaults(
            websiteName: $this->config->websiteName,
            charset: $this->config->charset,
            viewport: $this->config->viewport,
            rating: $this->config->rating,
            titleSeparator: $this->config->titleSeparator,
        );
    }
}
