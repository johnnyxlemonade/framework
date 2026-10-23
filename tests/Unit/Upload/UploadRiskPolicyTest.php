<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Upload;

use Lemonade\Framework\Mime\MimeRisk;
use Lemonade\Framework\Upload\UploadRiskPolicy;
use Lemonade\Framework\Upload\UploadVisibility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UploadRiskPolicyTest extends TestCase
{
    #[DataProvider('publicRiskProvider')]
    public function testPublicPolicyCannotBeWeakenedByAllowedRisks(
        MimeRisk $risk,
        bool $expected,
    ): void {
        $policy = new UploadRiskPolicy();

        self::assertSame(
            $expected,
            $policy->allows($risk, UploadVisibility::Public, MimeRisk::cases()),
        );
    }

    /**
     * @return iterable<string, array{MimeRisk, bool}>
     */
    public static function publicRiskProvider(): iterable
    {
        yield 'passive' => [MimeRisk::Passive, true];
        yield 'container' => [MimeRisk::Container, true];
        yield 'active content' => [MimeRisk::ActiveContent, false];
        yield 'executable' => [MimeRisk::Executable, false];
        yield 'unclassified' => [MimeRisk::Unclassified, false];
    }

    #[DataProvider('privateRiskProvider')]
    public function testPrivatePolicyRequiresEveryNonPassiveRiskExplicitly(MimeRisk $risk): void
    {
        $policy = new UploadRiskPolicy();

        self::assertFalse($policy->allows($risk, UploadVisibility::Private));
        self::assertTrue($policy->allows($risk, UploadVisibility::Private, [$risk]));
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
            UploadVisibility::Private,
        ));
    }
}
