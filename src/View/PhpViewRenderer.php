<?php

declare(strict_types=1);

namespace Lemonade\Framework\View;

use Lemonade\Framework\Core\Http\ResponseBuilder;
use Psr\Http\Message\ResponseInterface;

final class PhpViewRenderer implements ViewRendererInterface
{
    public function __construct(
        private readonly View $view,
        private readonly ResponseBuilder $responses,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], int $status = 200): ResponseInterface
    {
        return $this->responses->html($this->content($template, $data), $status);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function content(string $template, array $data = []): string
    {
        return $this->view->render($template, $data);
    }
}
