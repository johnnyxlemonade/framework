<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class BreadcrumbsConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'breadcrumbs';
    }

    public function frontendRoot(string $label, string $url): self
    {
        return $this->set('frontend.root_label', $label)->set('frontend.root_url', $url);
    }

    public function adminRoot(string $label, string $url): self
    {
        return $this->set('admin.root_label', $label)->set('admin.root_url', $url);
    }

    /**
     * @param array<string, string> $classes
     */
    public function classes(array $classes): self
    {
        return $this->set('classes', $classes);
    }
}
