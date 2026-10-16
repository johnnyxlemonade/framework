<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session;

use Lemonade\Framework\Session\Native\NativeSession;
use Lemonade\Framework\Session\Storage\SessionStorageInterface;
use PHPUnit\Framework\TestCase;

final class NativeSessionTest extends TestCase
{
    public function testDestroyDelegatesToTheStorageLifecycleContract(): void
    {
        $storage = $this->createMock(SessionStorageInterface::class);
        $storage->expects(self::once())->method('destroy');
        $storage->expects(self::never())->method('clear');
        $session = new NativeSession($storage);

        $session->destroy();
    }
}
