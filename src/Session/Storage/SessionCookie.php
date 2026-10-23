<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Storage;

use Closure;
use Lemonade\Framework\Http\Request\ResolvedRequestContext;
use Lemonade\Framework\Session\Cookie\SessionCookieSameSite;
use Lemonade\Framework\Session\Cookie\SessionCookieSecure;
use RuntimeException;

/**
 * Sends and expires the browser cookie that carries one session identifier.
 *
 * @internal
 */
final readonly class SessionCookie
{
    /**
     * @var Closure(string, string, array<string, int|string|bool>): bool
     */
    private Closure $emitter;

    /**
     * Defines the host-only cookie identity and its sliding lifetime.
     *
     * @param null|Closure(string, string, array<string, int|string|bool>): bool $emitter
     */
    public function __construct(
        private string $name,
        private int $lifetimeSeconds,
        private SessionCookieSameSite $sameSite = SessionCookieSameSite::Lax,
        ?Closure $emitter = null,
        private SessionCookieSecure $secure = SessionCookieSecure::Auto,
        private ?ResolvedRequestContext $requestContext = null,
    ) {
        $this->emitter = $emitter ?? static fn(string $name, string $value, array $options): bool => setcookie(
            $name,
            $value,
            $options,
        );
    }

    /**
     * Sends the active identifier with a fresh persistent expiry.
     */
    public function send(string $sessionId): void
    {
        $this->assertCanChange();

        if (($this->emitter)($this->name, $sessionId, $this->options(time() + $this->lifetimeSeconds)) !== true) {
            throw new RuntimeException(sprintf(
                'Failed to send session cookie "%s".',
                $this->name,
            ));
        }
    }

    /**
     * Expires the transport cookie so the browser no longer presents the destroyed identity.
     */
    public function expire(): void
    {
        $this->assertCanChange();

        if (($this->emitter)($this->name, '', $this->options(time() - 3600)) !== true) {
            throw new RuntimeException(sprintf(
                'Failed to expire session cookie "%s".',
                $this->name,
            ));
        }
    }

    /**
     * Returns the equivalent parameters required by PHP's native session runtime.
     *
     * @return array{
     *     lifetime: int,
     *     path: string,
     *     secure: bool,
     *     httponly: bool,
     *     samesite: 'Lax'|'Strict'|'None'
     * }
     */
    public function nativeParameters(): array
    {
        $secure = $this->secureForSameSitePolicy();

        return [
            'lifetime' => $this->lifetimeSeconds,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $this->sameSite->value,
        ];
    }

    /**
     * Fails before an identity-changing operation can diverge from browser state.
     */
    public function assertCanChange(): void
    {
        if (headers_sent($file, $line)) {
            throw new RuntimeException(sprintf(
                'Cannot change session cookie "%s" because headers were already sent in %s on line %d.',
                $this->name,
                $file,
                $line,
            ));
        }

        $this->secureForSameSitePolicy();
    }

    /**
     * @return array{
     *     expires: int,
     *     path: string,
     *     secure: bool,
     *     httponly: bool,
     *     samesite: 'Lax'|'Strict'|'None'
     * }
     */
    private function options(int $expires): array
    {
        $secure = $this->secureForSameSitePolicy();

        return [
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $this->sameSite->value,
        ];
    }

    private function secureForSameSitePolicy(): bool
    {
        $secure = match ($this->secure) {
            SessionCookieSecure::Always => true,
            SessionCookieSecure::Auto => $this->requestContext?->secure()
                ?? throw new RuntimeException(sprintf(
                    'Cannot resolve Secure for session cookie "%s" without a request context; use cookie.secure=always outside HTTP requests.',
                    $this->name,
                )),
        };

        if ($this->sameSite === SessionCookieSameSite::None && !$secure) {
            throw new RuntimeException(sprintf(
                'Cannot send session cookie "%s" with SameSite=None over an insecure request; Secure is required.',
                $this->name,
            ));
        }

        return $secure;
    }
}
