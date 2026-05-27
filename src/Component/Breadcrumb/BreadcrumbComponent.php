<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb;

final class BreadcrumbComponent
{
    public function __construct(
        private readonly BreadcrumbFactory $factory,
        private readonly BreadcrumbRenderer $renderer,
    ) {}

    public function frontend(string $currentLabel, ?string $currentUrl = null): BreadcrumbTrail
    {
        return $this->factory->createFrontend($currentLabel, $currentUrl);
    }

    public function admin(string $currentLabel, ?string $currentUrl = null): BreadcrumbTrail
    {
        return $this->factory->createAdmin($currentLabel, $currentUrl);
    }

    public function empty(): BreadcrumbTrail
    {
        return $this->factory->empty();
    }

    public function render(?BreadcrumbTrail $trail): string
    {
        return $this->renderer->render($trail);
    }
}
