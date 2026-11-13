<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Upload;

use Lemonade\Framework\Mime\MimeRisk;
use Lemonade\Framework\Upload\UploadRiskPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UploadRiskPolicyTest extends TestCase
{
    #[DataProvider('privateRiskProvider')]
    public function testPrivatePolicyRequiresEveryNonPassiveRiskExplicitly(MimeRisk $risk): void
    {
        $policy = new UploadRiskPolicy();

        self::assertFalse($policy->allows($risk));
        self::assertTrue($policy->allows($risk, [$risk]));
    }

    /**
     * @return iterable<string, array{MimeRisk}>
     */
    public static function privateRiskProvider(): iterable
    {
        yield 'container' => [MimeRisk::Container];
        yield 'active content' => [MimeRisk::ActiveContent];
        yield 'executable' => [MimeRisk::Executable];
        yield 'unclassified' => [MimeRisk::Unclassified];
    }

    public function testPrivatePolicyAllowsPassiveFormatsWithoutAdditionalConfiguration(): void
    {
        self::assertTrue((new UploadRiskPolicy())->allows(
            MimeRisk::Passive,
        ));
    }
}
