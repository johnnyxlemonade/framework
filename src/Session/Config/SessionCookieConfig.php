<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

use InvalidArgumentException;
use Lemonade\Framework\Session\Cookie\SessionCookieSameSite;
use Lemonade\Framework\Session\Cookie\SessionCookieSecure;

/**
 * Carries the browser cookie identity and SameSite policy for one session.
 */
final readonly class SessionCookieConfig
{
    /**
     * Defines the cookie identity, cross-site policy, and transport-security policy.
     */
    public function __construct(
        private string $name,
        private SessionCookieSameSite $sameSite,
        private SessionCookieSecure $secure = SessionCookieSecure::Auto,
    ) {
        if (
            preg_match('/^[A-Za-z0-9_-]+$/D', $name) !== 1
            || preg_match('/[A-Za-z]/', $name) !== 1
        ) {
            throw new InvalidArgumentException(
                'Session cookie name must contain a letter and use only letters, numbers, underscores, or hyphens.',
            );
        }
    }

    /**
     * Returns the browser cookie name that transports the session identifier.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the browser cross-site delivery policy for the session cookie.
     */
    public function sameSite(): SessionCookieSameSite
    {
        return $this->sameSite;
    }

    /**
     * Returns whether Secure follows the verified request or is always enabled.
     */
    public function secure(): SessionCookieSecure
    {
        return $this->secure;
    }
}
