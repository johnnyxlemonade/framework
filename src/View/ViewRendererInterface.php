<?php

declare(strict_types=1);

namespace Lemonade\Framework\View;

use Psr\Http\Message\ResponseInterface;

interface ViewRendererInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], int $status = 200): ResponseInterface;

    /**
     * @param array<string, mixed> $data
     */
    public function content(string $template, array $data = []): string;
}
