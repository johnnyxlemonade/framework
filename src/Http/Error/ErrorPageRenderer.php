<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Error;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Http\Config\ErrorConfig;
use Lemonade\Framework\View\View;
use Throwable;

final class ErrorPageRenderer
{
    public function __construct(
        private readonly ApplicationContext $context,
        private readonly ErrorConfig $config,
        private readonly ContainerInterface $container,
    ) {
    }

    public function notFound(Throwable $exception): string
    {
        $template = $this->config->notFoundView;

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
        $template = $this->config->internalServerErrorView;

        $page = $this->renderSafely(
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

        if (!$this->context->isDevelopment()) {
            return $page;
        }

        return $page . $this->developerDiagnostics($exception);
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
            'debug' => $this->context->isDevelopment(),
            'exception_class' => $this->context->isDevelopment() ? $exception::class : null,
            'exception_message' => $this->context->isDevelopment() ? $exception->getMessage() : null,
            'exception_trace' => $this->context->isDevelopment() ? $exception->getTraceAsString() : null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderSafely(string $template, array $data, string $fallback): string
    {
        try {
            $view = $this->container->get(View::class);
            $content = $view->render($template, $data);

            if (trim($content) === '') {
                return $fallback;
            }

            return $view->render('layouts/error', [
                ...$data,
                'content' => $content,
            ]);
        } catch (Throwable) {
            return $fallback;
        }
    }

    private function fallback(string $title, string $message, Throwable $exception): string
    {
        if (!$this->context->isDevelopment()) {
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

    private function developerDiagnostics(Throwable $exception): string
    {
        return sprintf(
            "\n<section class=\"framework-error-diagnostics\"><pre>%s</pre></section>",
            $this->escape(sprintf(
                "%s: %s\n\n%s",
                $exception::class,
                $exception->getMessage(),
                $exception->getTraceAsString(),
            )),
        );
    }
}
