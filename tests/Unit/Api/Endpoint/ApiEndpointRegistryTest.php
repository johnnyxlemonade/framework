<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Api\Endpoint;

use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use PHPUnit\Framework\TestCase;

final class ApiEndpointRegistryTest extends TestCase
{
    public function testRegistersEndpointAndFindsByNameAndRequest(): void
    {
        $registry = new ApiEndpointRegistry();
        $endpoint = $registry->add('GET', '/users', 'UsersController@index', 'users.index', 'Users', 'List users', ApiAccess::Protected);

        self::assertSame($endpoint, $registry->findByName('users.index'));
        self::assertSame($endpoint, $registry->findByRequest('GET', '/users'));
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
}
