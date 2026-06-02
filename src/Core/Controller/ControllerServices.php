<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Controller;

use Lemonade\Framework\Component\Breadcrumb\BreadcrumbComponent;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\Flash\FlashBagInterface;
use Lemonade\Framework\Upload\UploadFactory;
use Lemonade\Framework\Validation\FormValidation;
use Lemonade\Framework\View\RequestViewHelpers;
use Lemonade\Framework\View\View;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class ControllerServices
{
    /** @var array<class-string, object> */
    private array $services = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ServerRequestInterface $request,
    ) {}

    public function context(): ApplicationContext
    {
        return $this->service(ApplicationContext::class, 'ApplicationContext service is not available.');
    }

    public function breadcrumb(): BreadcrumbComponent
    {
        return $this->service(BreadcrumbComponent::class, 'BreadcrumbComponent service is not available.');
    }

    public function router(): Router
    {
        return $this->service(Router::class, 'Router service is not available.');
    }

    public function url(): UrlGenerator
    {
        return $this->service(UrlGenerator::class, 'UrlGenerator service is not available.');
    }

    public function validator(): FormValidation
    {
        return $this->service(FormValidation::class, 'FormValidation service is not available.');
    }

    public function upload(): UploadFactory
    {
        return $this->service(UploadFactory::class, 'UploadFactory service is not available.');
    }

    public function translator(): TranslatorInterface
    {
        return $this->service(TranslatorInterface::class, 'Translator service is not available.');
    }

    public function filesystem(): Filesystem
    {
        return $this->service(Filesystem::class, 'Filesystem service is not available.');
    }

    public function view(): View
    {
        $view = $this->service(View::class, 'View service is not available.');
        $view->shareOnce('requestHelpers', new RequestViewHelpers(
            request: $this->request,
            urlGenerator: $this->service(UrlGenerator::class, 'UrlGenerator service is not available.'),
            flash: $this->optionalService(FlashBagInterface::class),
            session: $this->optionalService(SessionInterface::class),
        ));

        return $view;
    }

    public function flash(): FlashBagInterface
    {
        return $this->service(FlashBagInterface::class, 'FlashBag service is not available.');
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    private function service(string $id, string $missingMessage): object
    {
        if (isset($this->services[$id])) {
            /** @var T $cached */
            $cached = $this->services[$id];

            return $cached;
        }

        if (!$this->container->isBound($id)) {
            throw new RuntimeException($missingMessage);
        }

        $service = $this->container->get($id);

        if (!$service instanceof $id) {
            throw new RuntimeException($missingMessage);
        }

        $this->services[$id] = $service;

        return $service;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T|null
     */
    private function optionalService(string $id): ?object
    {
        if (!$this->container->isBound($id)) {
            return null;
        }

        $service = $this->container->get($id);

        return $service instanceof $id ? $service : null;
    }
}
