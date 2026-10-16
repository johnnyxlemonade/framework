<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

use InvalidArgumentException;
use Lemonade\Framework\Session\Cookie\SessionCookieSameSite;

/**
 * Carries the browser cookie identity and SameSite policy for one session.
 */
final readonly class SessionCookieConfig
{
    /**
     * Defines the cookie name and browser cross-site delivery policy.
     */
    public function __construct(
        private string $name,
        private SessionCookieSameSite $sameSite,
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
}
