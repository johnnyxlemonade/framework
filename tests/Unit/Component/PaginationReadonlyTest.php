<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Component;

use Lemonade\Framework\Component\Pagination\PaginationComponent;
use Lemonade\Framework\Component\Pagination\PaginationFactory;
use Lemonade\Framework\Component\Pagination\PaginationRenderer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PaginationReadonlyTest extends TestCase
{
    public function testStatelessPaginationServicesAreReadonly(): void
    {
        $classes = [
            PaginationComponent::class,
            PaginationFactory::class,
            PaginationRenderer::class,
        ];

        foreach ($classes as $class) {
            self::assertTrue(
                (new ReflectionClass($class))->isReadOnly(),
                sprintf('%s must remain readonly.', $class),
            );
        }
    }
}
