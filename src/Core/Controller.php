<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Component\Breadcrumb\BreadcrumbComponent;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Core\Http\ResponseBuilder;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Http\Request\HttpMethod;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Session\Flash\FlashBagInterface;
use Lemonade\Framework\Support\ServiceLocator;
use Lemonade\Framework\Upload\UploadFactory;
use Lemonade\Framework\Validation\FormValidation;
use Lemonade\Framework\View\View;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;

abstract class Controller
{
    private ?ServerRequestInterface $requestContext = null;
    private ?RequestData $requestData = null;
    private ?ResponseBuilder $responseBuilder = null;
    private ?BreadcrumbComponent $breadcrumbComponent = null;
    private ?Router $routerService = null;
    private ?UrlGenerator $urlGenerator = null;
    private ?FormValidation $formValidation = null;
    private ?UploadFactory $uploadFactory = null;
    private ?TranslatorInterface $translatorService = null;
    private ?Filesystem $filesystemService = null;
    private ?View $viewService = null;
    private ?FlashBagInterface $flashBagService = null;
    private ?ApplicationContext $applicationContext = null;

    final public function setControllerContext(
        ServerRequestInterface $request,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
    ): void {
        $this->requestContext = $request;
        $this->requestData = new RequestData($request);
        $this->responseBuilder = new ResponseBuilder($responseFactory, $streamFactory);
        $this->breadcrumbComponent = null;
        $this->routerService = null;
        $this->urlGenerator = null;
        $this->formValidation = null;
        $this->uploadFactory = null;
        $this->translatorService = null;
        $this->filesystemService = null;
        $this->viewService = null;
        $this->flashBagService = null;
        $this->applicationContext = null;
    }

    protected function request(): ServerRequestInterface
    {
        return $this->requireRequestContext();
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        return $this->requireRequestData()->input($key, $default);
    }

    protected function query(string $key, mixed $default = null): mixed
    {
        return $this->requireRequestData()->query($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    protected function queryAll(): array
    {
        return $this->requireRequestData()->queryAll();
    }

    protected function post(string $key, mixed $default = null): mixed
    {
        return $this->requireRequestData()->post($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    protected function postAll(): array
    {
        return $this->requireRequestData()->postAll();
    }

    protected function header(string $name, ?string $default = null): ?string
    {
        return $this->requireRequestData()->header($name, $default);
    }

    /**
     * @return array<string, string[]>
     */
    protected function headers(): array
    {
        return $this->requireRequestData()->headers();
    }

    protected function cookie(string $name, mixed $default = null): mixed
    {
        return $this->requireRequestData()->cookie($name, $default);
    }

    /**
     * @return array<string, mixed>
     */
    protected function cookies(): array
    {
        return $this->requireRequestData()->cookies();
    }

    protected function server(string $key, mixed $default = null): mixed
    {
        return $this->requireRequestData()->server($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serverAll(): array
    {
        return $this->requireRequestData()->serverAll();
    }

    protected function body(): string
    {
        return $this->requireRequestData()->body();
    }

    protected function jsonInput(string $key, mixed $default = null): mixed
    {
        return $this->requireRequestData()->jsonInput($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonPayload(): array
    {
        return $this->requireRequestData()->jsonPayload();
    }

    protected function isJsonRequest(): bool
    {
        return $this->requireRequestData()->isJsonRequest();
    }

    protected function acceptsJson(): bool
    {
        return $this->requireRequestData()->acceptsJson();
    }

    protected function expectsJson(): bool
    {
        return $this->requireRequestData()->expectsJson();
    }

    /**
     * @return UploadedFileInterface|array<string, mixed>|null
     */
    protected function file(string $name): UploadedFileInterface|array|null
    {
        return $this->requireRequestData()->file($name);
    }

    /**
     * @return array<string, UploadedFileInterface|array<string, mixed>>
     */
    protected function files(): array
    {
        return $this->requireRequestData()->files();
    }

    protected function text(string $content, int $status = 200): ResponseInterface
    {
        return $this->requireResponseBuilder()->text($content, $status);
    }

    protected function html(string $content, int $status = 200): ResponseInterface
    {
        return $this->requireResponseBuilder()->html($content, $status);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function json(array $payload, int $status = 200): ResponseInterface
    {
        return $this->requireResponseBuilder()->json($payload, $status);
    }

    protected function redirect(string $to, int $status = 302): ResponseInterface
    {
        return $this->requireResponseBuilder()->redirect($to, $status);
    }

    protected function download(
        string $filePath,
        ?string $downloadName = null,
        string $contentType = 'application/octet-stream',
    ): ResponseInterface {
        return $this->requireResponseBuilder()->download($filePath, $downloadName, $contentType);
    }

    protected function response(
        string $content = '',
        int $status = 200,
        string $contentType = 'text/html; charset=UTF-8',
    ): ResponseInterface {
        return $this->requireResponseBuilder()->response($content, $status, $contentType);
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
        return $this->requireResponseBuilder()->stream($producer, $status, $contentType, $headers);
    }

    protected function isAjaxRequest(): bool
    {
        return $this->requireRequestData()->isAjaxRequest();
    }

    protected function ip(): ?string
    {
        return $this->requireRequestData()->ip();
    }

    protected function userAgent(?string $default = null): ?string
    {
        return $this->requireRequestData()->userAgent($default);
    }

    protected function referer(?string $default = null): ?string
    {
        return $this->requireRequestData()->referer($default);
    }

    protected function method(): string
    {
        return $this->requireRequestData()->method();
    }

    protected function isMethod(HttpMethod|string $method): bool
    {
        return $this->requireRequestData()->isMethod($method);
    }

    protected function isGet(): bool
    {
        return $this->requireRequestData()->isGet();
    }

    protected function isPost(): bool
    {
        return $this->requireRequestData()->isPost();
    }

    protected function isPut(): bool
    {
        return $this->requireRequestData()->isPut();
    }

    protected function isPatch(): bool
    {
        return $this->requireRequestData()->isPatch();
    }

    protected function isDelete(): bool
    {
        return $this->requireRequestData()->isDelete();
    }

    protected function isHead(): bool
    {
        return $this->requireRequestData()->isHead();
    }

    protected function isOptions(): bool
    {
        return $this->requireRequestData()->isOptions();
    }

    protected function inputString(string $key, string $default = ''): string
    {
        return $this->requireRequestData()->inputString($key, $default);
    }

    protected function inputInt(string $key, int $default = 0): int
    {
        return $this->requireRequestData()->inputInt($key, $default);
    }

    protected function inputFloat(string $key, float $default = 0.0): float
    {
        return $this->requireRequestData()->inputFloat($key, $default);
    }

    protected function inputBool(string $key, bool $default = false): bool
    {
        return $this->requireRequestData()->inputBool($key, $default);
    }

    protected function breadcrumb(): BreadcrumbComponent
    {
        if ($this->breadcrumbComponent instanceof BreadcrumbComponent) {
            return $this->breadcrumbComponent;
        }

        $service = $this->frameworkService(BreadcrumbComponent::class);
        if (!$service instanceof BreadcrumbComponent) {
            throw new \RuntimeException('BreadcrumbComponent service is not available.');
        }

        $this->breadcrumbComponent = $service;

        return $this->breadcrumbComponent;
    }

    protected function router(): Router
    {
        if ($this->routerService instanceof Router) {
            return $this->routerService;
        }

        $service = $this->frameworkService(Router::class);
        if (!$service instanceof Router) {
            throw new \RuntimeException('Router service is not available.');
        }

        $this->routerService = $service;

        return $this->routerService;
    }

    protected function url(): UrlGenerator
    {
        if ($this->urlGenerator instanceof UrlGenerator) {
            return $this->urlGenerator;
        }

        $service = $this->frameworkService(UrlGenerator::class);
        if (!$service instanceof UrlGenerator) {
            throw new \RuntimeException('UrlGenerator service is not available.');
        }

        $this->urlGenerator = $service;

        return $this->urlGenerator;
    }

    protected function validator(): FormValidation
    {
        if ($this->formValidation instanceof FormValidation) {
            return $this->formValidation;
        }

        $service = $this->frameworkService(FormValidation::class);
        if (!$service instanceof FormValidation) {
            throw new \RuntimeException('FormValidation service is not available.');
        }

        $this->formValidation = $service;

        return $this->formValidation;
    }

    protected function upload(): UploadFactory
    {
        if ($this->uploadFactory instanceof UploadFactory) {
            return $this->uploadFactory;
        }

        $service = $this->frameworkService(UploadFactory::class);
        if (!$service instanceof UploadFactory) {
            throw new \RuntimeException('UploadFactory service is not available.');
        }

        $this->uploadFactory = $service;

        return $this->uploadFactory;
    }

    protected function translator(): TranslatorInterface
    {
        if ($this->translatorService instanceof TranslatorInterface) {
            return $this->translatorService;
        }

        $service = $this->frameworkService(TranslatorInterface::class);
        if (!$service instanceof TranslatorInterface) {
            throw new \RuntimeException('Translator service is not available.');
        }

        $this->translatorService = $service;

        return $this->translatorService;
    }

    protected function filesystem(): Filesystem
    {
        if ($this->filesystemService instanceof Filesystem) {
            return $this->filesystemService;
        }

        $service = $this->frameworkService(Filesystem::class);
        if (!$service instanceof Filesystem) {
            throw new \RuntimeException('Filesystem service is not available.');
        }

        $this->filesystemService = $service;

        return $this->filesystemService;
    }

    protected function view(): View
    {
        if ($this->viewService instanceof View) {
            return $this->viewService;
        }

        $service = $this->frameworkService(View::class);
        if (!$service instanceof View) {
            throw new \RuntimeException('View service is not available.');
        }

        $this->viewService = $service;

        return $this->viewService;
    }

    protected function flash(): FlashBagInterface
    {
        if ($this->flashBagService instanceof FlashBagInterface) {
            return $this->flashBagService;
        }

        $service = $this->frameworkService(FlashBagInterface::class);
        if (!$service instanceof FlashBagInterface) {
            throw new \RuntimeException('FlashBag service is not available.');
        }

        $this->flashBagService = $service;

        return $this->flashBagService;
    }

    protected function context(): ApplicationContext
    {
        if ($this->applicationContext instanceof ApplicationContext) {
            return $this->applicationContext;
        }

        $service = $this->frameworkService(ApplicationContext::class);

        if (!$service instanceof ApplicationContext) {
            throw new \RuntimeException('ApplicationContext service is not available.');
        }

        $this->applicationContext = $service;

        return $this->applicationContext;
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

    private function frameworkService(string $id): mixed
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }
        /** @var non-empty-string $id */

        $container = ServiceLocator::container();

        if ($container === null || !$container->has($id)) {
            return null;
        }

        return $container->get($id);
    }

    private function requireRequestContext(): ServerRequestInterface
    {
        if (!$this->requestContext instanceof ServerRequestInterface) {
            throw new \RuntimeException('Controller context is not initialized. Missing ServerRequestInterface.');
        }

        return $this->requestContext;
    }

    private function requireRequestData(): RequestData
    {
        if (!$this->requestData instanceof RequestData) {
            throw new \RuntimeException('Controller context is not initialized. Missing RequestData.');
        }

        return $this->requestData;
    }

    private function requireResponseBuilder(): ResponseBuilder
    {
        if (!$this->responseBuilder instanceof ResponseBuilder) {
            throw new \RuntimeException('Controller context is not initialized. Missing ResponseBuilder.');
        }

        return $this->responseBuilder;
    }
}
