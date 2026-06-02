<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Component\Breadcrumb\BreadcrumbComponent;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Controller\ControllerContext;
use Lemonade\Framework\Core\Controller\ControllerResponses;
use Lemonade\Framework\Core\Controller\ControllerServices;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Http\Request\HttpMethod;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Session\Flash\FlashBagInterface;
use Lemonade\Framework\Upload\UploadFactory;
use Lemonade\Framework\Validation\FormValidation;
use Lemonade\Framework\View\View;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

abstract class AbstractController
{
    private ?ControllerContext $controllerContext = null;
    private ?ControllerResponses $controllerResponses = null;
    private ?ControllerServices $controllerServices = null;

    final public function setControllerContext(
        ServerRequestInterface $request,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        ContainerInterface $container,
    ): void {
        $this->controllerContext = new ControllerContext(
            request: $request,
            responseFactory: $responseFactory,
            streamFactory: $streamFactory,
        );

        $this->controllerResponses = new ControllerResponses(
            $this->controllerContext->responseBuilder(),
        );

        $this->controllerServices = new ControllerServices($container, $request);
    }

    protected function request(): ServerRequestInterface
    {
        return $this->runtime()->request();
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        return $this->requestData()->input($key, $default);
    }

    protected function query(string $key, mixed $default = null): mixed
    {
        return $this->requestData()->query($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    protected function queryAll(): array
    {
        return $this->requestData()->queryAll();
    }

    protected function post(string $key, mixed $default = null): mixed
    {
        return $this->requestData()->post($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    protected function postAll(): array
    {
        return $this->requestData()->postAll();
    }

    protected function header(string $name, ?string $default = null): ?string
    {
        return $this->requestData()->header($name, $default);
    }

    /**
     * @return array<string, string[]>
     */
    protected function headers(): array
    {
        return $this->requestData()->headers();
    }

    protected function cookie(string $name, mixed $default = null): mixed
    {
        return $this->requestData()->cookie($name, $default);
    }

    /**
     * @return array<string, mixed>
     */
    protected function cookies(): array
    {
        return $this->requestData()->cookies();
    }

    protected function server(string $key, mixed $default = null): mixed
    {
        return $this->requestData()->server($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serverAll(): array
    {
        return $this->requestData()->serverAll();
    }

    protected function body(): string
    {
        return $this->requestData()->body();
    }

    protected function jsonInput(string $key, mixed $default = null): mixed
    {
        return $this->requestData()->jsonInput($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonPayload(): array
    {
        return $this->requestData()->jsonPayload();
    }

    protected function isJsonRequest(): bool
    {
        return $this->requestData()->isJsonRequest();
    }

    protected function acceptsJson(): bool
    {
        return $this->requestData()->acceptsJson();
    }

    protected function expectsJson(): bool
    {
        return $this->requestData()->expectsJson();
    }

    /**
     * @return UploadedFileInterface|array<string, mixed>|null
     */
    protected function file(string $name): UploadedFileInterface|array|null
    {
        return $this->requestData()->file($name);
    }

    /**
     * @return array<string, UploadedFileInterface|array<string, mixed>>
     */
    protected function files(): array
    {
        return $this->requestData()->files();
    }

    protected function isAjaxRequest(): bool
    {
        return $this->requestData()->isAjaxRequest();
    }

    protected function ip(): ?string
    {
        return $this->requestData()->ip();
    }

    protected function userAgent(?string $default = null): ?string
    {
        return $this->requestData()->userAgent($default);
    }

    protected function referer(?string $default = null): ?string
    {
        return $this->requestData()->referer($default);
    }

    protected function method(): string
    {
        return $this->requestData()->method();
    }

    protected function isMethod(HttpMethod|string $method): bool
    {
        return $this->requestData()->isMethod($method);
    }

    protected function isGet(): bool
    {
        return $this->requestData()->isGet();
    }

    protected function isPost(): bool
    {
        return $this->requestData()->isPost();
    }

    protected function isPut(): bool
    {
        return $this->requestData()->isPut();
    }

    protected function isPatch(): bool
    {
        return $this->requestData()->isPatch();
    }

    protected function isDelete(): bool
    {
        return $this->requestData()->isDelete();
    }

    protected function isHead(): bool
    {
        return $this->requestData()->isHead();
    }

    protected function isOptions(): bool
    {
        return $this->requestData()->isOptions();
    }

    protected function inputString(string $key, string $default = ''): string
    {
        return $this->requestData()->inputString($key, $default);
    }

    protected function inputInt(string $key, int $default = 0): int
    {
        return $this->requestData()->inputInt($key, $default);
    }

    protected function inputFloat(string $key, float $default = 0.0): float
    {
        return $this->requestData()->inputFloat($key, $default);
    }

    protected function inputBool(string $key, bool $default = false): bool
    {
        return $this->requestData()->inputBool($key, $default);
    }

    protected function text(string $content, int $status = 200): ResponseInterface
    {
        return $this->responses()->text($content, $status);
    }

    protected function html(string $content, int $status = 200): ResponseInterface
    {
        return $this->responses()->html($content, $status);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function json(array $payload, int $status = 200): ResponseInterface
    {
        return $this->responses()->json($payload, $status);
    }

    protected function redirect(string $to, int $status = 302): ResponseInterface
    {
        return $this->responses()->redirect($to, $status);
    }

    protected function download(
        string $filePath,
        ?string $downloadName = null,
        string $contentType = 'application/octet-stream',
    ): ResponseInterface {
        return $this->responses()->download($filePath, $downloadName, $contentType);
    }

    protected function response(
        string $content = '',
        int $status = 200,
        string $contentType = 'text/html; charset=UTF-8',
    ): ResponseInterface {
        return $this->responses()->response($content, $status, $contentType);
    }

    /**
     * @param callable():void $producer
     * @param array<string, string> $headers
     */
    protected function stream(
        callable $producer,
        int $status = 200,
        string $contentType = 'text/plain; charset=UTF-8',
        array $headers = [],
    ): ResponseInterface {
        return $this->responses()->stream($producer, $status, $contentType, $headers);
    }

    protected function app(): ApplicationContext
    {
        return $this->services()->context();
    }

    protected function breadcrumb(): BreadcrumbComponent
    {
        return $this->services()->breadcrumb();
    }

    protected function router(): Router
    {
        return $this->services()->router();
    }

    protected function url(): UrlGenerator
    {
        return $this->services()->url();
    }

    protected function validator(): FormValidation
    {
        return $this->services()->validator();
    }

    protected function upload(): UploadFactory
    {
        return $this->services()->upload();
    }

    protected function translator(): TranslatorInterface
    {
        return $this->services()->translator();
    }

    protected function filesystem(): Filesystem
    {
        return $this->services()->filesystem();
    }

    protected function view(): View
    {
        return $this->services()->view();
    }

    protected function flash(): FlashBagInterface
    {
        return $this->services()->flash();
    }

    protected function setLang(?string $locale): void
    {
        $this->translator()->setLocale($locale);
        $this->validator()->setLocale($locale);
    }

    /**
     * @param array<string, scalar|null> $replacements
     */
    protected function trans(string $key, array $replacements = [], ?string $locale = null): string
    {
        return $this->translator()->get($key, $replacements, $locale);
    }

    /**
     * @return array<string, string>
     */
    protected function transGroup(string $group, ?string $locale = null): array
    {
        return $this->translator()->group($group, $locale);
    }

    private function runtime(): ControllerContext
    {
        if (!$this->controllerContext instanceof ControllerContext) {
            throw new RuntimeException('Controller context is not initialized. Missing ControllerContext.');
        }

        return $this->controllerContext;
    }

    private function requestData(): RequestData
    {
        return $this->runtime()->requestData();
    }

    private function responses(): ControllerResponses
    {
        if (!$this->controllerResponses instanceof ControllerResponses) {
            throw new RuntimeException('Controller context is not initialized. Missing ControllerResponses.');
        }

        return $this->controllerResponses;
    }

    private function services(): ControllerServices
    {
        if (!$this->controllerServices instanceof ControllerServices) {
            throw new RuntimeException('Controller context is not initialized. Missing ControllerServices.');
        }

        return $this->controllerServices;
    }
}
