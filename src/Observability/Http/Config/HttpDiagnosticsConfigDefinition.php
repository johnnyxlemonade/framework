<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Http\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Defines one configuration layer for explicitly exposing HTTP diagnostic headers.
 */
final class HttpDiagnosticsConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Creates a layer without overriding earlier settings.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Identifies this definition in the typed configuration registry.
     */
    public static function moduleKey(): string
    {
        return 'http_diagnostics';
    }

    /**
     * Sets whether diagnostic headers may expose performance data to the client.
     */
    public function enabled(bool $enabled = true): self
    {
        return $this->set('enabled', $enabled);
    }
}
