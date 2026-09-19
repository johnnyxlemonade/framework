<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Property\Container;

use Eris\Generators;
use Eris\TestTrait;
use Lemonade\Framework\Container\Container;
use PHPUnit\Framework\TestCase;

final class TaggedServicesPropertiesTest extends TestCase
{
    use TestTrait;

    private const PROPERTY_CASES = 500;

    public function testTaggedServicesPreserveDeclarationOrder(): void
    {
        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(Generators::choose(1, 40))
            ->then(function (int $count): void {
                $container = new Container();
                $expectedIds = [];

                for ($index = 0; $index < $count; $index++) {
                    $serviceId = 'tagged.service.' . $index;
                    $container->singleton($serviceId, new TaggedServiceFixture($index));
                    $container->tag($serviceId, 'capability.example');
                    $expectedIds[] = $serviceId;
                }

                self::assertSame($expectedIds, array_keys($this->taggedServices($container->tagged('capability.example'))));
            });
    }

    public function testTagMembershipDoesNotDependOnResolutionOrder(): void
    {
        $this
            ->limitTo(self::PROPERTY_CASES)
            ->forAll(Generators::choose(1, 40), Generators::bool())
            ->then(function (int $count, bool $reverseResolution): void {
                $container = new Container();
                $serviceIds = [];

                for ($index = 0; $index < $count; $index++) {
                    $serviceId = 'tagged.service.' . $index;
                    $container->singleton($serviceId, new TaggedServiceFixture($index));
                    $container->tag($serviceId, 'capability.example');
                    $serviceIds[] = $serviceId;
                }

                $resolutionOrder = $reverseResolution ? array_reverse($serviceIds) : $serviceIds;
                foreach ($resolutionOrder as $serviceId) {
                    $container->get($serviceId);
                }

                self::assertSame($serviceIds, array_keys($this->taggedServices($container->tagged('capability.example'))));
            });
    }

    /**
     * @param iterable<string, object> $services
     * @return array<string, object>
     */
    private function taggedServices(iterable $services): array
    {
        $resolved = [];

        foreach ($services as $serviceId => $service) {
            $resolved[$serviceId] = $service;
        }

        return $resolved;
    }
}

final class TaggedServiceFixture
{
    public function __construct(
        public readonly int $id,
    ) {}
}
