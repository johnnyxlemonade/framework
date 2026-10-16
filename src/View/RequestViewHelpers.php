<?php

declare(strict_types=1);

namespace Lemonade\Framework\View;

use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\Session\Flash\FlashBagInterface;
use Lemonade\Framework\Session\SessionNamespace;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Exposes request-local URL, old-input and flash operations to rendered views.
 */
final readonly class RequestViewHelpers
{
    private const string OLD_INPUT_NAMESPACE = 'old_input';

    private ?SessionNamespace $oldInput;

    /**
     * Captures one request and optional default-session state for the renderer's active scope.
     */
    public function __construct(
        private ServerRequestInterface $request,
        private UrlGenerator $urlGenerator,
        private ?FlashBagInterface $flash = null,
        ?SessionInterface $session = null,
    ) {
        $this->oldInput = $session instanceof SessionInterface
            ? new SessionNamespace($session, self::OLD_INPUT_NAMESPACE)
            : null;
    }

    /**
     * Resolves canonical session old input before flash and caller fallbacks.
     */
    public function old(string $key, mixed $default = ''): mixed
    {
        if ($this->oldInput instanceof SessionNamespace) {
            $value = $this->oldInput->get($key);
            if ($value !== null) {
                return $value;
            }
        }

        if (!$this->flash instanceof FlashBagInterface) {
            return $default;
        }

        $values = $this->flash->get('old_input', []);
        if (!is_array($values)) {
            return $default;
        }

        return $values[$key] ?? $default;
    }

    /**
     * Pulls one transient value from the optional flash bag.
     */
    public function flash(string $key, mixed $default = null): mixed
    {
        if (!$this->flash instanceof FlashBagInterface) {
            return $default;
        }

        return $this->flash->pull($key, $default);
    }

    /**
     * Returns the active request path without its query string.
     */
    public function currentPath(): string
    {
        return $this->request->getUri()->getPath();
    }

    /**
     * Returns the active request query string without a leading question mark.
     */
    public function currentQuery(): string
    {
        return $this->request->getUri()->getQuery();
    }

    /**
     * Returns the active request URI or only its normalized path.
     */
    public function currentUrl(bool $withQuery = true): string
    {
        $uri = $this->request->getUri();
        if ($withQuery) {
            return (string) $uri;
        }

        $path = $uri->getPath();

        return $path !== '' ? $path : '/';
    }

    /**
     * Returns the active absolute request URL with optional query removal.
     */
    public function currentFullUrl(bool $withQuery = true): string
    {
        $uri = $this->request->getUri();

        if ($withQuery) {
            return (string) $uri;
        }

        return $uri->getScheme() . '://' . $uri->getAuthority() . $uri->getPath();
    }

    /**
     * Compares a URL against the active path using exact or prefix matching.
     */
    public function isUrlActive(string $url, bool $startsWith = false): bool
    {
        $current = $this->currentUrl(false);

        if ($startsWith) {
            return str_starts_with(rtrim($current, '/'), rtrim($url, '/'));
        }

        return rtrim($current, '/') === rtrim($url, '/');
    }

    /**
     * Generates a route URL and compares it with the active request path.
     *
     * @param array<string, scalar|null> $params
     */
    public function isRouteActive(string $route, array $params = [], bool $startsWith = false): bool
    {
        return $this->isUrlActive(
            $this->urlGenerator->route($route, $params),
            $startsWith,
        );
    }
}
