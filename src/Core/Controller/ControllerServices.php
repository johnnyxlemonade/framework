<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Controller;

use Lemonade\Framework\Component\Breadcrumb\BreadcrumbComponent;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Session\Flash\FlashBagInterface;
use Lemonade\Framework\Support\ServiceLocator;
use Lemonade\Framework\Upload\UploadFactory;
use Lemonade\Framework\Validation\FormValidation;
use Lemonade\Framework\View\View;
use RuntimeException;

final class ControllerServices
{
    /** @var array<class-string, object> */
    private array $services = [];

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
        return $this->service(View::class, 'View service is not available.');
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

        $container = ServiceLocator::container();

        if ($container === null) {
            throw new RuntimeException($missingMessage);
        }

        $service = $container->get($id);

        if (!$service instanceof $id) {
            throw new RuntimeException($missingMessage);
        }

        $this->services[$id] = $service;

        return $service;
    }
}
