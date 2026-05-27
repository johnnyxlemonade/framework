<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb;

final class BreadcrumbFactory
{
    public function __construct(
        private readonly string $frontendRootLabel = 'Home',
        private readonly string $frontendRootUrl = '/',
        private readonly string $adminRootLabel = 'Admin',
        private readonly string $adminRootUrl = '/admin',
    ) {}

    public function createFrontend(string $currentLabel, ?string $currentUrl = null): BreadcrumbTrail
    {
        return (new BreadcrumbTrail())
            ->add($this->frontendRootLabel, $this->frontendRootUrl)
            ->add($currentLabel, $currentUrl, true);
    }

    public function createAdmin(string $currentLabel, ?string $currentUrl = null): BreadcrumbTrail
    {
        return (new BreadcrumbTrail())
            ->add($this->adminRootLabel, $this->adminRootUrl)
            ->add($currentLabel, $currentUrl, true);
    }

    public function empty(): BreadcrumbTrail
    {
        return new BreadcrumbTrail();
    }
}
