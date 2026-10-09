<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta;

use Lemonade\Framework\Component\Meta\Config\MetaConfig;

/**
 * Applies configured defaults to page metadata and creates its renderer.
 *
 * Its configured defaults remain fixed for the lifetime of the component.
 */
final readonly class MetaComponent
{
    /**
     * Initializes the component with the defaults shared by rendered pages.
     */
    public function __construct(
        private MetaConfig $config,
    ) {
    }

    /**
     * Returns a mutable renderer for metadata after applying unset configured defaults.
     */
    public function make(MetaData $data): MetaFactory
    {
        return new MetaFactory($this->applyDefaults($data));
    }

    /**
     * Renders metadata directly, applying configured defaults when given raw metadata.
     */
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
