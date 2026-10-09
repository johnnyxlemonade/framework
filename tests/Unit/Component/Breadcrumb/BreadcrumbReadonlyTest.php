<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Component\Breadcrumb;

use Lemonade\Framework\Component\Breadcrumb\BreadcrumbComponent;
use Lemonade\Framework\Component\Breadcrumb\BreadcrumbRenderer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BreadcrumbReadonlyTest extends TestCase
{
    public function testStatelessBreadcrumbServicesAreReadonly(): void
    {
        $classes = [
            BreadcrumbComponent::class,
            BreadcrumbRenderer::class,
        ];

        foreach ($classes as $class) {
            self::assertTrue(
                (new ReflectionClass($class))->isReadOnly(),
                sprintf('%s must remain readonly.', $class),
            );
        }
    }
}
