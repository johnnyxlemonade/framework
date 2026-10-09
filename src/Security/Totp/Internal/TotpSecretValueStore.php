<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Totp\Internal;

use Lemonade\Framework\Security\Totp\TotpSecret;
use LogicException;
use WeakMap;

/**
 * Keeps secret bytes out of object properties so PHP representation helpers cannot export them.
 *
 * @internal
 */
final class TotpSecretValueStore
{
    /**
     * @var WeakMap<TotpSecret, string>|null
     */
    private static ?WeakMap $values = null;

    /**
     * Associates canonical secret material with its owning value object without extending its lifetime.
     */
    public static function remember(TotpSecret $secret, string $base32): void
    {
        self::$values ??= new WeakMap();
        self::$values[$secret] = $base32;
    }

    /**
     * Returns secret material only for a value object created through the TOTP contract.
     */
    public static function valueFor(TotpSecret $secret): string
    {
        $value = self::$values[$secret] ?? null;

        if (!is_string($value)) {
            throw new LogicException('TOTP secret value is unavailable.');
        }

        return $value;
    }
}
