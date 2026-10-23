<?php

declare(strict_types=1);

namespace Lemonade\Framework\View;

use Closure;
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
     * @var null|Closure(string): string
     */
    private ?Closure $absoluteUrl;

    /**
     * Captures one request and optional default-session state for the renderer's active scope.
     *
     * @param null|callable(string): string $absoluteUrl
     */
    public function __construct(
        private ServerRequestInterface $request,
        private UrlGenerator $urlGenerator,
        private ?FlashBagInterface $flash = null,
        ?SessionInterface $session = null,
        ?callable $absoluteUrl = null,
    ) {
        $this->oldInput = $session instanceof SessionInterface
            ? new SessionNamespace($session, self::OLD_INPUT_NAMESPACE)
            : null;
        $this->absoluteUrl = $absoluteUrl !== null
            ? Closure::fromCallable($absoluteUrl)
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
     * Returns the active request path with an optional query string.
     */
    public function currentUrl(bool $withQuery = true): string
    {
        $uri = $this->request->getUri();
        $path = $uri->getPath();
        $path = $path !== '' ? $path : '/';

        if ($withQuery && $uri->getQuery() !== '') {
            return $path . '?' . $uri->getQuery();
        }

        return $path;
    }

    /**
     * Returns an absolute request URL only through the configured safe origin resolver.
     *
     * @throws \LogicException When no absolute URL resolver was supplied.
     */
    public function currentFullUrl(bool $withQuery = true): string
    {
        if (!$this->absoluteUrl instanceof Closure) {
            throw new \LogicException(
                'Absolute request URLs require an explicit base URL or an allowlisted request host.',
            );
        }

        $url = ($this->absoluteUrl)($this->currentPath());
        $query = $this->currentQuery();

        return $withQuery && $query !== '' ? $url . '?' . $query : $url;
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
