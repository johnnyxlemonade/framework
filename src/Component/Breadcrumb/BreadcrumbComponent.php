<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb;

/**
 * Creates application-supplied breadcrumb trails and renders them through the
 * framework's canonical breadcrumb renderer.
 */
final class BreadcrumbComponent
{
    /**
     * Initializes the component with the renderer shared by views and callers.
     */
    public function __construct(
        private readonly BreadcrumbRenderer $renderer,
    ) {
    }

    /**
     * Starts an empty trail so the caller can define its own navigation roots and items.
     */
    public function empty(): BreadcrumbTrail
    {
        return new BreadcrumbTrail();
    }

    /**
     * Renders a trail or returns an empty string when no trail is available.
     */
    public function render(?BreadcrumbTrail $trail): string
    {
        return $this->renderer->render($trail);
    }
}
