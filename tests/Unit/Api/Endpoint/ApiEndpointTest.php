<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Api\Endpoint;

use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpoint;
use Lemonade\Framework\Api\Endpoint\ApiEndpointMetadata;
use Lemonade\Framework\Routing\ControllerAction;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ApiEndpointTest extends TestCase
{
    public function testEndpointWithoutMetadataUsesDefaultMetadata(): void
    {
        $endpoint = new ApiEndpoint(
            method: 'GET',
            path: '/users',
            controllerAction: ControllerAction::for('UsersController', 'index'),
            name: 'users.index',
            summary: 'Users',
            description: 'List users',
            access: ApiAccess::Public,
        );

        self::assertSame([], $endpoint->metadata()->tags());
        self::assertSame([], $endpoint->metadata()->scopes());
        self::assertSame([200], $endpoint->metadata()->successStatusCodes());
    }

    public function testEndpointWithMetadataExposesValues(): void
    {
        $metadata = new ApiEndpointMetadata(
            tags: ['Users'],
            scopes: ['users:read'],
            parameters: [['name' => 'id', 'in' => 'path']],
            requestBodySchema: ['type' => 'object'],
            responseSchema: ['type' => 'object'],
            successStatusCodes: [200, 201],
        );

        $endpoint = new ApiEndpoint(
            method: 'GET',
            path: '/users',
            controllerAction: ControllerAction::for('UsersController', 'index'),
            name: 'users.index',
            summary: 'Users',
            description: 'List users',
            access: ApiAccess::Protected,
            metadata: $metadata,
        );

        self::assertSame(['Users'], $endpoint->metadata()->tags());
        self::assertSame(['users:read'], $endpoint->metadata()->scopes());
        self::assertSame([['name' => 'id', 'in' => 'path']], $endpoint->metadata()->parameters());
        self::assertSame(['type' => 'object'], $endpoint->metadata()->requestBodySchema());
        self::assertSame(['type' => 'object'], $endpoint->metadata()->responseSchema());
        self::assertSame([200, 201], $endpoint->metadata()->successStatusCodes());
    }

    #[DataProvider('invalidEndpointFieldProvider')]
    public function testEndpointRejectsInvalidBaseFields(
        string $method,
        string $path,
        string $name,
        string $summary,
    ): void {
        $this->expectException(\InvalidArgumentException::class);
        $reflection = new \ReflectionClass(ApiEndpoint::class);
        $reflection->newInstanceArgs([
            $method,
            $path,
            ControllerAction::for('UsersController', 'index'),
            $name,
            $summary,
            'List users',
            ApiAccess::Protected,
            null,
        ]);
    }

    public function testMetadataRejectsInvalidStatusCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $reflection = new \ReflectionClass(ApiEndpointMetadata::class);
        $reflection->newInstanceArgs([[], [], [], null, null, [99]]);
    }

    public function testMetadataRejectsEmptySuccessStatusCodes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ApiEndpointMetadata(successStatusCodes: []);
    }

    /**
     * @return list<array{string, string, string, string}>
     */
    public static function invalidEndpointFieldProvider(): array
    {
        return [
            ['', '/users', 'users.index', 'Users'],
            ['GET', '', 'users.index', 'Users'],
            ['GET', '/users', '', 'Users'],
            ['GET', '/users', 'users.index', ''],
        ];
    }
}
