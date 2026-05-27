<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Routing;

use InvalidArgumentException;
use Lemonade\Framework\Http\Request\HttpMethod;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testGetRegistersRouteWithMethodPathControllerAndAction(): void
    {
        $router = new Router();
        $route = $router->get('/users', 'UserController@index');

        self::assertSame('GET', $route->method());
        self::assertSame('/users', $route->path());
        self::assertSame('App\\Controllers\\UserController', $route->controller());
        self::assertSame('index', $route->action());
    }

    public function testPostPutPatchDeleteMapExpectedMethods(): void
    {
        $router = new Router();

        self::assertSame('POST', $router->post('/a', 'AController@store')->method());
        self::assertSame('PUT', $router->put('/a', 'AController@update')->method());
        self::assertSame('PATCH', $router->patch('/a', 'AController@patch')->method());
        self::assertSame('DELETE', $router->delete('/a', 'AController@delete')->method());
    }

    public function testMapAcceptsHttpMethodEnumAndString(): void
    {
        $router = new Router();
        $enumRoute = $router->map(HttpMethod::PATCH, '/enum', 'EnumController@patch');
        $stringRoute = $router->map('options', '/string', 'StringController@options');

        self::assertSame('PATCH', $enumRoute->method());
        self::assertSame('OPTIONS', $stringRoute->method());
    }

    public function testHandlerWithoutAtThrowsInvalidArgumentException(): void
    {
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);
        $router->get('/broken', 'BrokenHandler');
    }

    public function testHandlerWithEmptyControllerThrowsInvalidArgumentException(): void
    {
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);
        $router->get('/broken', '@index');
    }

    public function testHandlerWithEmptyActionThrowsInvalidArgumentException(): void
    {
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);
        $router->get('/broken', 'UserController@');
    }

    public function testNamedRouteCanBeGeneratedViaUrl(): void
    {
        $router = new Router();
        $router->getNamed('users.index', '/users', 'UserController@index');

        self::assertSame('/users', $router->url('users.index'));
    }

    public function testDuplicateNamedRouteThrowsLogicException(): void
    {
        $router = new Router();
        $router->getNamed('users.index', '/users', 'UserController@index');

        $this->expectException(\LogicException::class);
        $router->getNamed('users.index', '/users/all', 'UserController@all');
    }

    public function testUrlInjectsRouteParameters(): void
    {
        $router = new Router();
        $router->getNamed('users.show', '/users/{id}', 'UserController@show');

        self::assertSame('/users/15', $router->url('users.show', ['id' => 15]));
    }

    public function testUrlAddsUnusedParamsAsQueryString(): void
    {
        $router = new Router();
        $router->getNamed('users.show', '/users/{id}', 'UserController@show');

        self::assertSame(
            '/users/15?tab=settings&sort=desc',
            $router->url('users.show', ['id' => 15, 'tab' => 'settings', 'sort' => 'desc']),
        );
    }

    public function testUrlThrowsOnMissingRouteParameter(): void
    {
        $router = new Router();
        $router->getNamed('users.show', '/users/{id}', 'UserController@show');

        $this->expectException(InvalidArgumentException::class);
        $router->url('users.show', []);
    }

    public function testUrlThrowsOnNullRouteParameter(): void
    {
        $router = new Router();
        $router->getNamed('users.show', '/users/{id}', 'UserController@show');

        $this->expectException(InvalidArgumentException::class);
        $router->url('users.show', ['id' => null]);
    }

    public function testGroupPrefixesAllRoutesInsideGroup(): void
    {
        $router = new Router();
        $group = $router->group('/admin', function (Router $router): void {
            $router->get('/users', 'UserController@index');
            $router->get('/settings', 'SettingsController@index');
        });

        $paths = array_map(
            static fn(\Lemonade\Framework\Routing\Route $route): string => $route->path(),
            $group->routes(),
        );

        self::assertSame(['/admin/users', '/admin/settings'], $paths);
    }

    public function testGroupReturnsRouteGroupWithCreatedRoutes(): void
    {
        $router = new Router();
        $group = $router->group('/api', function (Router $router): void {
            $router->get('/one', 'OneController@index');
            $router->post('/two', 'TwoController@store');
        });

        self::assertCount(2, $group->routes());
        self::assertSame('GET', $group->routes()[0]->method());
        self::assertSame('POST', $group->routes()[1]->method());
    }

    public function testMatchFindsExactRoute(): void
    {
        $router = new Router();
        $router->get('/users', 'UserController@index');

        $match = $router->match(new ServerRequest('GET', '/users'));

        self::assertSame('App\\Controllers\\UserController', $match->controller());
        self::assertSame('index', $match->action());
        self::assertSame([], $match->params());
    }

    public function testMatchExtractsSimplePathParameter(): void
    {
        $router = new Router();
        $router->get('/users/{id}', 'UserController@show');

        $match = $router->match(new ServerRequest('GET', '/users/99'));

        self::assertSame(['id' => '99'], $match->params());
    }

    public function testMatchExtractsWildcardParameter(): void
    {
        $router = new Router();
        $router->get('/docs/{slug:any}', 'DocsController@show');

        $match = $router->match(new ServerRequest('GET', '/docs/guides/install/windows'));

        self::assertSame(['slug' => 'guides/install/windows'], $match->params());
    }

    public function testMatchThrowsRouteNotFoundExceptionWhenNoRouteMatches(): void
    {
        $router = new Router();
        $router->get('/users', 'UserController@index');

        $this->expectException(RouteNotFoundException::class);
        $router->match(new ServerRequest('GET', '/missing'));
    }
}
