<?php

declare(strict_types=1);

namespace Lemonade\Framework\View;

use Lemonade\Framework\Image\ImageViewHelper;
use Lemonade\Framework\Image\ImageViewOptions;
use Lemonade\Framework\Image\Value\ImageReference;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Lemonade\Framework\Support\BaseUrlResolver;

/**
 * Exposes URL, localization, image, and session-backed CSRF operations to one request's views.
 */
final readonly class ViewHelpers
{
    /**
     * Binds app-scoped utilities and the current request's CSRF helper into one template API.
     */
    public function __construct(
        private BaseUrlResolver $baseUrl,
        private UrlGenerator $urlGenerator,
        private CsrfViewHelper $csrf,
        private TranslatorInterface $translator,
        private LocalizationConfig $config,
        private ?ImageViewHelper $images = null,
    ) {
    }

    /**
     * Resolves an application asset path against the configured public base URL.
     */
    public function asset(string $path): string
    {
        return $this->baseUrl->baseUrl($path);
    }

    /**
     * Renders an image reference through the optional image integration.
     *
     * @throws \LogicException when image services are not configured
     */
    public function image(?ImageReference $reference, ?ImageViewOptions $options = null): string
    {
        if ($this->images === null) {
            throw new \LogicException('Image view helper is not configured.');
        }
        return $this->images->image($reference, $options);
    }

    /**
     * Generates a URL for a named route.
     *
     * @param array<string, scalar|null> $params
     */
    public function url(string $route, array $params = []): string
    {
        return $this->urlGenerator->route($route, $params);
    }

    /**
     * Generates a locale-aware URL for a named route.
     *
     * @param array<string, scalar|null> $params
     */
    public function localizedUrl(string $route, array $params = [], ?string $locale = null): string
    {
        return $this->urlGenerator->localizedRoute($route, $params, $locale);
    }

    /**
     * Renders an escaped hidden field containing a token from the current session.
     */
    public function csrfField(string $name = 'default'): string
    {
        return $this->csrf->field($name);
    }

    /**
     * Returns a named CSRF token from the current session.
     */
    public function csrfToken(string $name = 'default'): string
    {
        return $this->csrf->token($name);
    }

    /**
     * Translates one message with scalar replacements.
     *
     * @param array<string, scalar|null> $replacements
     */
    public function lang(string $key, array $replacements = [], ?string $locale = null): string
    {
        return $this->translator->get($key, $replacements, $locale);
    }

    /**
     * Returns the active runtime locale or a configured or caller-provided fallback.
     */
    public function currentLocale(string $default = 'en'): string
    {
        $runtime = $this->translator->locale();
        if (is_string($runtime) && trim($runtime) !== '') {
            return trim($runtime);
        }

        $configured = trim($this->config->defaultLocale);

        return $configured !== '' ? $configured : $default;
    }

    /**
     * Returns one complete translation group for a locale.
     *
     * @return array<string, string>
     */
    public function langGroup(string $group, ?string $locale = null): array
    {
        return $this->translator->group($group, $locale);
    }

    /**
     * Returns every translation group for a locale.
     *
     * @return array<string, array<string, string>>
     */
    public function langAll(?string $locale = null): array
    {
        return $this->translator->all($locale);
    }
}
