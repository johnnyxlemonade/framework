<?php

declare(strict_types=1);

namespace Lemonade\Framework\Security\Totp;

/**
 * Selects the HMAC hash used by a TOTP credential and its provisioning representation.
 */
enum TotpAlgorithm: string
{
    case SHA1 = 'sha1';
    case SHA256 = 'sha256';
    case SHA512 = 'sha512';

}
