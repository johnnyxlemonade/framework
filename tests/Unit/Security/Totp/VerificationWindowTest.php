<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Security\Totp;

use Lemonade\Framework\Security\Totp\Exception\InvalidTotpConfigurationException;
use Lemonade\Framework\Security\Totp\VerificationWindow;
use PHPUnit\Framework\TestCase;

final class VerificationWindowTest extends TestCase
{
    public function testDefaultsToOneStepInEachDirection(): void
    {
        $window = new VerificationWindow();

        self::assertSame(1, $window->pastSteps);
        self::assertSame(1, $window->futureSteps);
    }

    public function testAcceptsTheFixedMaximumInEachDirection(): void
    {
        $window = new VerificationWindow(2, 2);

        self::assertSame(2, $window->pastSteps);
        self::assertSame(2, $window->futureSteps);
    }

    public function testRejectsAnUnboundedPastWindow(): void
    {
        $this->expectException(InvalidTotpConfigurationException::class);

        new VerificationWindow(VerificationWindow::MAXIMUM_STEPS_PER_DIRECTION + 1, 0);
    }

    public function testRejectsNegativeFutureWindow(): void
    {
        $this->expectException(InvalidTotpConfigurationException::class);

        new VerificationWindow(0, -1);
    }
}
