<?php

declare(strict_types=1);

namespace Lemonade\Framework\View;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Lemonade\Framework\Support\BaseUrlResolver;

final class ViewHelpers
{
    public function __construct(
        private readonly BaseUrlResolver $baseUrl,
        private readonly UrlGenerator $urlGenerator,
        private readonly CsrfViewHelper $csrf,
        private readonly TranslatorInterface $translator,
        private readonly Config $config,
    ) {}

    public function asset(string $path): string
    {
        return $this->baseUrl->baseUrl($path);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function url(string $route, array $params = []): string
    {
        return $this->urlGenerator->route($route, $params);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function localizedUrl(string $route, array $params = [], ?string $locale = null): string
    {
        return $this->urlGenerator->localizedRoute($route, $params, $locale);
    }

    public function csrfField(string $name = 'default'): string
    {
        return $this->csrf->field($name);
    }

    public function csrfToken(string $name = 'default'): string
    {
        return $this->csrf->token($name);
    }

    /**
     * @param array<string, scalar|null> $replacements
     */
    public function lang(string $key, array $replacements = [], ?string $locale = null): string
    {
        return $this->translator->get($key, $replacements, $locale);
    }

    public function currentLocale(string $default = 'en'): string
    {
        $runtime = $this->translator->locale();
        if (is_string($runtime) && trim($runtime) !== '') {
            return trim($runtime);
        }

        $configuredValue = $this->config->get('localization.default_locale', $default);
        $configured = is_scalar($configuredValue) ? trim((string) $configuredValue) : $default;

        return $configured !== '' ? $configured : $default;
    }

    /**
     * @return array<string, string>
     */
    public function langGroup(string $group, ?string $locale = null): array
    {
        return $this->translator->group($group, $locale);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function langAll(?string $locale = null): array
    {
        return $this->translator->all($locale);
    }
}
