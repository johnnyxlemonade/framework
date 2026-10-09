<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Security\Totp;

use Lemonade\Framework\Security\Totp\TotpAlgorithm;
use Lemonade\Framework\Security\Totp\TotpConfiguration;
use Lemonade\Framework\Security\Totp\TotpProvisioningUriFactory;
use Lemonade\Framework\Security\Totp\TotpSecret;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TotpProvisioningUriFactoryTest extends TestCase
{
    public function testBuildsAnRfc3986EncodedProvisioningUri(): void
    {
        $uri = (new TotpProvisioningUriFactory())->create(
            'Acme & Sons',
            'alice+admin@example.test',
            $this->secret(),
            new TotpConfiguration(TotpAlgorithm::SHA256, 45, 8),
        );

        self::assertSame(
            'otpauth://totp/Acme%20%26%20Sons%3Aalice%2Badmin%40example.test?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ&issuer=Acme%20%26%20Sons&algorithm=SHA256&digits=8&period=45',
            $uri,
        );
    }

    public function testRequiresAnIssuer(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TotpProvisioningUriFactory())->create('', 'alice@example.test', $this->secret(), new TotpConfiguration());
    }

    public function testRequiresAnAccountLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TotpProvisioningUriFactory())->create('Acme', ' ', $this->secret(), new TotpConfiguration());
    }

    private function secret(): TotpSecret
    {
        return TotpSecret::fromBase32('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');
    }
}
