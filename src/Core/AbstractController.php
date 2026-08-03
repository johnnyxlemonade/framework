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

/**
 * Base controller for framework-managed HTTP controllers.
 *
 * The controller exposes the current PSR-7 request, request data helpers,
 * response builders, and commonly used framework services. Its runtime
 * controller context is initialized by the controller resolver before the
 * action method is invoked, so the class is not intended for standalone use
 * outside the framework dispatch process.
 */
abstract class AbstractController
{
    private ?ControllerContext $controllerContext = null;
    private ?ControllerResponses $controllerResponses = null;
    private ?ControllerServices $controllerServices = null;

    /**
     * Initializes the controller runtime context before an action is executed.
     *
     * This framework-managed entrypoint stores the current request, response
     * factory, stream factory, and service container, then creates the
     * controller context, response helpers, and service accessors used by
     * derived controllers.
     */
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

    /**
     * Returns the current PSR-7 server request for the dispatched action.
     */
    protected function request(): ServerRequestInterface
    {
        return $this->runtime()->request();
    }

    /**
     * Returns a combined request input value.
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        return $this->requestData()->input($key, $default);
    }

    /**
     * Returns a query string parameter.
     */
    protected function query(string $key, mixed $default = null): mixed
    {
        return $this->requestData()->query($key, $default);
    }

    /**
     * Returns all query string parameters.
     *
     * @return array<string, mixed>
     */
    protected function queryAll(): array
    {
        return $this->requestData()->queryAll();
    }

    /**
     * Returns a parsed body value.
     */
    protected function post(string $key, mixed $default = null): mixed
    {
        return $this->requestData()->post($key, $default);
    }

    /**
     * Returns all parsed body values.
     *
     * @return array<string, mixed>
     */
    protected function postAll(): array
    {
        return $this->requestData()->postAll();
    }

    /**
     * Returns a request header value.
     */
    protected function header(string $name, ?string $default = null): ?string
    {
        return $this->requestData()->header($name, $default);
    }

    /**
     * Returns all request headers grouped by header name.
     *
     * @return array<string, string[]>
     */
    protected function headers(): array
    {
        return $this->requestData()->headers();
    }

    /**
     * Returns a cookie parameter.
     */
    protected function cookie(string $name, mixed $default = null): mixed
    {
        return $this->requestData()->cookie($name, $default);
    }

    /**
     * Returns all cookie parameters.
     *
     * @return array<string, mixed>
     */
    protected function cookies(): array
    {
        return $this->requestData()->cookies();
    }

    /**
     * Returns a server parameter.
     */
    protected function server(string $key, mixed $default = null): mixed
    {
        return $this->requestData()->server($key, $default);
    }

    /**
     * Returns all server parameters.
     *
     * @return array<string, mixed>
     */
    protected function serverAll(): array
    {
        return $this->requestData()->serverAll();
    }

    /**
     * Returns the raw request body.
     */
    protected function body(): string
    {
        return $this->requestData()->body();
    }

    /**
     * Returns a value from the decoded JSON request payload.
     */
    protected function jsonInput(string $key, mixed $default = null): mixed
    {
        return $this->requestData()->jsonInput($key, $default);
    }

    /**
     * Returns the decoded JSON request payload.
     *
     * @return array<string, mixed>
     */
    protected function jsonPayload(): array
    {
        return $this->requestData()->jsonPayload();
    }

    /**
     * Reports whether the request content type is JSON.
     */
    protected function isJsonRequest(): bool
    {
        return $this->requestData()->isJsonRequest();
    }

    /**
     * Reports whether the request Accept header allows JSON responses.
     */
    protected function acceptsJson(): bool
    {
        return $this->requestData()->acceptsJson();
    }

    /**
     * Reports whether the request should be treated as expecting JSON.
     */
    protected function expectsJson(): bool
    {
        return $this->requestData()->expectsJson();
    }

    /**
     * Returns an uploaded file entry by name.
     *
     * @return UploadedFileInterface|array<string, mixed>|null
     */
    protected function file(string $name): UploadedFileInterface|array|null
    {
        return $this->requestData()->file($name);
    }

    /**
     * Returns all uploaded files indexed by input name.
     *
     * @return array<string, UploadedFileInterface|array<string, mixed>>
     */
    protected function files(): array
    {
        return $this->requestData()->files();
    }

    /**
     * Reports whether the request was made through AJAX/XHR semantics.
     */
    protected function isAjaxRequest(): bool
    {
        return $this->requestData()->isAjaxRequest();
    }

    /**
     * Returns the resolved client IP address when available.
     */
    protected function ip(): ?string
    {
        return $this->requestData()->ip();
    }

    /**
     * Returns the User-Agent header value.
     */
    protected function userAgent(?string $default = null): ?string
    {
        return $this->requestData()->userAgent($default);
    }

    /**
     * Returns the Referer header value.
     */
    protected function referer(?string $default = null): ?string
    {
        return $this->requestData()->referer($default);
    }

    /**
     * Returns the normalized HTTP method.
     */
    protected function method(): string
    {
        return $this->requestData()->method();
    }

    /**
     * Reports whether the current request uses the given HTTP method.
     */
    protected function isMethod(HttpMethod|string $method): bool
    {
        return $this->requestData()->isMethod($method);
    }

    /**
     * Reports whether the request method is GET.
     */
    protected function isGet(): bool
    {
        return $this->requestData()->isGet();
    }

    /**
     * Reports whether the request method is POST.
     */
    protected function isPost(): bool
    {
        return $this->requestData()->isPost();
    }

    /**
     * Reports whether the request method is PUT.
     */
    protected function isPut(): bool
    {
        return $this->requestData()->isPut();
    }

    /**
     * Reports whether the request method is PATCH.
     */
    protected function isPatch(): bool
    {
        return $this->requestData()->isPatch();
    }

    /**
     * Reports whether the request method is DELETE.
     */
    protected function isDelete(): bool
    {
        return $this->requestData()->isDelete();
    }

    /**
     * Reports whether the request method is HEAD.
     */
    protected function isHead(): bool
    {
        return $this->requestData()->isHead();
    }

    /**
     * Reports whether the request method is OPTIONS.
     */
    protected function isOptions(): bool
    {
        return $this->requestData()->isOptions();
    }

    /**
     * Returns an input value converted to string semantics.
     */
    protected function inputString(string $key, string $default = ''): string
    {
        return $this->requestData()->inputString($key, $default);
    }

    /**
     * Returns an input value converted to integer semantics.
     */
    protected function inputInt(string $key, int $default = 0): int
    {
        return $this->requestData()->inputInt($key, $default);
    }

    /**
     * Returns an input value converted to float semantics.
     */
    protected function inputFloat(string $key, float $default = 0.0): float
    {
        return $this->requestData()->inputFloat($key, $default);
    }

    /**
     * Returns an input value converted to boolean semantics.
     */
    protected function inputBool(string $key, bool $default = false): bool
    {
        return $this->requestData()->inputBool($key, $default);
    }

    /**
     * Creates a plain text response.
     */
    protected function text(string $content, int $status = 200): ResponseInterface
    {
        return $this->responses()->text($content, $status);
    }

    /**
     * Creates an HTML response.
     */
    protected function html(string $content, int $status = 200): ResponseInterface
    {
        return $this->responses()->html($content, $status);
    }

    /**
     * Creates a JSON response from the provided payload.
     *
     * @param array<string, mixed> $payload
     */
    protected function json(array $payload, int $status = 200): ResponseInterface
    {
        return $this->responses()->json($payload, $status);
    }

    /**
     * Creates a redirect response.
     */
    protected function redirect(string $to, int $status = 302): ResponseInterface
    {
        return $this->responses()->redirect($to, $status);
    }

    /**
     * Creates a file download response.
     */
    protected function download(
        string $filePath,
        ?string $downloadName = null,
        string $contentType = 'application/octet-stream',
    ): ResponseInterface {
        return $this->responses()->download($filePath, $downloadName, $contentType);
    }

    /**
     * Creates a generic response with an explicit content type.
     */
    protected function response(
        string $content = '',
        int $status = 200,
        string $contentType = 'text/html; charset=UTF-8',
    ): ResponseInterface {
        return $this->responses()->response($content, $status, $contentType);
    }

    /**
     * Creates a streamed response produced by the given callback.
     *
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

    /**
     * Returns the application context available to the controller.
     */
    protected function app(): ApplicationContext
    {
        return $this->services()->context();
    }

    /**
     * Returns the breadcrumb component service.
     */
    protected function breadcrumb(): BreadcrumbComponent
    {
        return $this->services()->breadcrumb();
    }

    /**
     * Returns the framework router service.
     */
    protected function router(): Router
    {
        return $this->services()->router();
    }

    /**
     * Returns the framework URL generator.
     */
    protected function url(): UrlGenerator
    {
        return $this->services()->url();
    }

    /**
     * Returns the form validation service.
     */
    protected function validator(): FormValidation
    {
        return $this->services()->validator();
    }

    /**
     * Returns the upload factory service.
     */
    protected function upload(): UploadFactory
    {
        return $this->services()->upload();
    }

    /**
     * Returns the translator service.
     */
    protected function translator(): TranslatorInterface
    {
        return $this->services()->translator();
    }

    /**
     * Returns the filesystem service.
     */
    protected function filesystem(): Filesystem
    {
        return $this->services()->filesystem();
    }

    /**
     * Returns the view renderer service.
     */
    protected function view(): View
    {
        return $this->services()->view();
    }

    /**
     * Returns the flash message bag.
     */
    protected function flash(): FlashBagInterface
    {
        return $this->services()->flash();
    }

    /**
     * Returns a controller-scoped service resolved from the container.
     *
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    protected function controllerService(string $id): object
    {
        return $this->services()->get($id);
    }

    /**
     * Sets the active locale on both translator and validator services.
     */
    protected function setLang(?string $locale): void
    {
        $this->translator()->setLocale($locale);
        $this->validator()->setLocale($locale);
    }

    /**
     * Translates a localized key with optional replacements.
     *
     * @param array<string, scalar|null> $replacements
     */
    protected function trans(string $key, array $replacements = [], ?string $locale = null): string
    {
        return $this->translator()->get($key, $replacements, $locale);
    }

    /**
     * Returns the complete translation group for the given locale.
     *
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
