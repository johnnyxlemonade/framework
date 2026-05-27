<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Error;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\View\View;
use Throwable;

final class ErrorPageRenderer
{
    public function __construct(
        private readonly ApplicationContext $context,
        private readonly Config $config,
        private readonly View $view,
    ) {}

    public function notFound(Throwable $exception): string
    {
        $template = $this->config->string('error.views.not_found', 'errors/404') ?? 'errors/404';

        return $this->renderSafely(
            template: $template,
            data: $this->errorData(
                title: 'Stránka nebyla nalezena',
                message: 'Požadovaná stránka neexistuje, byla přesunuta nebo je adresa zadaná chybně.',
                exception: $exception,
            ),
            fallback: $this->fallback(
                title: '404 Not Found',
                message: 'Stránka nebyla nalezena.',
                exception: $exception,
            ),
        );
    }

    public function internalServerError(Throwable $exception): string
    {
        $template = $this->config->string('error.views.internal_server_error', 'errors/500') ?? 'errors/500';

        return $this->renderSafely(
            template: $template,
            data: $this->errorData(
                title: '500 Internal Server Error',
                message: 'Při zpracování požadavku došlo k neočekávané chybě.',
                exception: $exception,
            ),
            fallback: $this->fallback(
                title: '500 Internal Server Error',
                message: 'Došlo k chybě aplikace.',
                exception: $exception,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function errorData(
        string $title,
        string $message,
        Throwable $exception,
    ): array {
        return [
            'title' => $title,
            'message' => $message,
            'debug' => $this->context->debug(),
            'exception_class' => $this->context->debug() ? $exception::class : null,
            'exception_message' => $this->context->debug() ? $exception->getMessage() : null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderSafely(string $template, array $data, string $fallback): string
    {
        try {
            $content = $this->view->render($template, $data);

            if (trim($content) === '') {
                return $fallback;
            }

            return $this->view->render('layouts/error', [
                ...$data,
                'content' => $content,
            ]);
        } catch (Throwable) {
            return $fallback;
        }
    }

    private function fallback(string $title, string $message, Throwable $exception): string
    {
        if (!$this->context->debug()) {
            return sprintf(
                '<h1>%s</h1><p>%s</p>',
                $this->escape($title),
                $this->escape($message),
            );
        }

        return sprintf(
            "<h1>%s</h1>\n\n<pre>%s: %s</pre>",
            $this->escape($title),
            $this->escape($exception::class),
            $this->escape($exception->getMessage()),
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
