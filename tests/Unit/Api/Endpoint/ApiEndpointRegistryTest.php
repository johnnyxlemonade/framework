<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Api\Endpoint;

use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointMetadata;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use PHPUnit\Framework\TestCase;

final class ApiEndpointRegistryTest extends TestCase
{
    public function testRegistersEndpointAndFindsByNameAndRequest(): void
    {
        $registry = new ApiEndpointRegistry();
        $metadata = new ApiEndpointMetadata(
            tags: ['Users'],
            scopes: ['users:read'],
            successStatusCodes: [200, 206],
        );
        $endpoint = $registry->add('GET', '/users', 'UsersController@index', 'users.index', 'Users', 'List users', ApiAccess::Protected, $metadata);

        self::assertSame($endpoint, $registry->findByName('users.index'));
        self::assertSame($endpoint, $registry->findByRequest('GET', '/users'));
        self::assertSame(['Users'], $endpoint->metadata()->tags());
        self::assertSame(['users:read'], $endpoint->metadata()->scopes());
        self::assertSame([200, 206], $endpoint->metadata()->successStatusCodes());
    }

    public function testDuplicateNameThrowsLogicException(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->add('GET', '/users', 'UsersController@index', 'users.index', 'Users', 'List users');

        $this->expectException(\LogicException::class);
        $registry->add('GET', '/admins', 'AdminsController@index', 'users.index', 'Admins', 'List admins');
    }

    public function testDuplicateMethodPathThrowsLogicException(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->add('GET', '/users', 'UsersController@index', 'users.index', 'Users', 'List users');

        $this->expectException(\LogicException::class);
        $registry->add('GET', '/users', 'UsersController@other', 'users.other', 'Users', 'Other');
    }

    public function testHeadRequestFallsBackToGetEndpoint(): void
    {
        $registry = new ApiEndpointRegistry();
        $endpoint = $registry->add('GET', '/users/{id}', 'UsersController@show', 'users.show', 'User', 'Show user');

        self::assertSame($endpoint, $registry->findByRequest('HEAD', '/users/{id}'));
    }

    public function testVerbConvenienceMethodsRegisterExpectedMethod(): void
    {
        $registry = new ApiEndpointRegistry();

        self::assertSame('POST', $registry->post('/a', 'C@a', 'a.post', 'A', 'A')->method());
        self::assertSame('PUT', $registry->put('/b', 'C@b', 'b.put', 'B', 'B')->method());
        self::assertSame('PATCH', $registry->patch('/c', 'C@c', 'c.patch', 'C', 'C')->method());
        self::assertSame('DELETE', $registry->delete('/d', 'C@d', 'd.delete', 'D', 'D')->method());
    }

    public function testAllReturnsEndpointList(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->get('/a', 'C@a', 'a', 'A', 'A');
        $registry->get('/b', 'C@b', 'b', 'B', 'B');

        self::assertCount(2, $registry->all());
    }
}
