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

    public function testHeadAndHeadNamedMapExpectedMethod(): void
    {
        $router = new Router();

        self::assertSame('HEAD', $router->head('/health', 'HealthController@show')->method());
        self::assertSame('HEAD', $router->headNamed('health.check', '/health', 'HealthController@show')->method());
        self::assertSame('/health', $router->url('health.check'));
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

    public function testGroupPropagatesExceptionAndCleansUpPrefixForSubsequentRoutes(): void
    {
        $router = new Router();

        try {
            $router->group('/admin', static function (Router $router): void {
                $router->get('/users', 'UserController@index');

                throw new \RuntimeException('group failed');
            });

            self::fail('Expected group builder exception to be propagated.');
        } catch (\RuntimeException $exception) {
            self::assertSame('group failed', $exception->getMessage());
        }

        $route = $router->getNamed('status.check', '/status', 'StatusController@show');

        self::assertSame('/status', $route->path());
        self::assertSame('status.check', $route->name());
        self::assertSame('/status', $router->url('status.check'));

        $match = $router->match(new ServerRequest('GET', '/status'));

        self::assertSame('App\\Controllers\\StatusController', $match->controller());
        self::assertSame('show', $match->action());
    }

    public function testLocalizedGroupRegistersBaseAndLocalizedNamedRoutes(): void
    {
        $router = new Router();
        $group = $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('home.index', '', 'HomeController@index');
            $router->getNamed('documentation.show', '/documentation/{slug}', 'DocumentationController@show');
        });

        self::assertCount(2, $group->plain()->routes());
        self::assertCount(2, $group->localized()->routes());
        self::assertSame('/', $router->url('home.index'));
        self::assertSame('/cs', $router->url('localized.home.index', ['locale' => 'cs']));
        self::assertSame('/documentation/abc', $router->url('documentation.show', ['slug' => 'abc']));
        self::assertSame('/cs/documentation/abc', $router->url('localized.documentation.show', ['locale' => 'cs', 'slug' => 'abc']));
    }

    public function testLocalizedGroupPropagatesExceptionAndCleansUpPrefixesForSubsequentNamedRoutes(): void
    {
        $router = new Router();
        $invocations = 0;

        try {
            $router->localizedGroup(static function (Router $router) use (&$invocations): void {
                $invocations++;
                $router->getNamed(
                    $invocations === 1 ? 'home.index' : 'localized.home.index',
                    '',
                    'HomeController@index',
                );

                if ($invocations === 2) {
                    throw new \RuntimeException('localized group failed');
                }
            });

            self::fail('Expected localizedGroup builder exception to be propagated.');
        } catch (\RuntimeException $exception) {
            self::assertSame('localized group failed', $exception->getMessage());
        }

        self::assertSame(2, $invocations);

        $route = $router->getNamed('status.check', '/status', 'StatusController@show');

        self::assertSame('status.check', $route->name());
        self::assertSame('/status', $route->path());
        self::assertSame('/status', $router->url('status.check'));

        $this->expectException(RouteNotFoundException::class);
        $router->url('localized.status.check', ['locale' => 'cs']);
    }

    public function testLocalizedGroupPlainAndLocalizedSubsetsExcludePreviouslyRegisteredRoutes(): void
    {
        $router = new Router();
        $router->getNamed('status.check', '/status', 'StatusController@show');

        $group = $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('home.index', '', 'HomeController@index');
            $router->getNamed('contact.index', '/contact', 'ContactController@index');
        });

        $plainNames = array_map(
            static function (\Lemonade\Framework\Routing\Route $route): string {
                $name = $route->name();

                self::assertIsString($name);

                return $name;
            },
            $group->plain()->routes(),
        );
        $localizedNames = array_map(
            static function (\Lemonade\Framework\Routing\Route $route): string {
                $name = $route->name();

                self::assertIsString($name);

                return $name;
            },
            $group->localized()->routes(),
        );

        self::assertSame(['home.index', 'contact.index'], $plainNames);
        self::assertSame(['localized.home.index', 'localized.contact.index'], $localizedNames);
    }

    public function testLocalizedGroupRespectsGroupPrefix(): void
    {
        $router = new Router();
        $router->group('/front', static function (Router $router): void {
            $router->localizedGroup(static function (Router $router): void {
                $router->getNamed('home.index', '', 'HomeController@index');
            });
        });

        self::assertSame('/front', $router->url('home.index'));
        self::assertSame('/front/cs', $router->url('localized.home.index', ['locale' => 'cs']));
    }

    public function testLocalizedGroupRespectsMiddleware(): void
    {
        $router = new Router();
        $group = $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('home.index', '', 'HomeController@index');
        });

        $group->middleware(\Lemonade\Framework\Security\Csrf\CsrfMiddleware::class);

        self::assertCount(2, $group->routes());
        self::assertSame(
            [\Lemonade\Framework\Security\Csrf\CsrfMiddleware::class],
            $group->routes()[0]->middlewareStack(),
        );
        self::assertSame(
            [\Lemonade\Framework\Security\Csrf\CsrfMiddleware::class],
            $group->routes()[1]->middlewareStack(),
        );
    }

    public function testLocalizedGroupPlainSubsetContainsOnlyPlainRoutes(): void
    {
        $router = new Router();
        $group = $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('home.index', '', 'HomeController@index');
            $router->getNamed('contact.index', '/contact', 'ContactController@index');
        });

        $paths = array_map(
            static fn(\Lemonade\Framework\Routing\Route $route): string => $route->path(),
            $group->plain()->routes(),
        );

        self::assertSame(['/', '/contact'], $paths);
    }

    public function testLocalizedGroupLocalizedSubsetContainsOnlyLocalizedRoutes(): void
    {
        $router = new Router();
        $group = $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('home.index', '', 'HomeController@index');
            $router->getNamed('contact.index', '/contact', 'ContactController@index');
        });

        $paths = array_map(
            static fn(\Lemonade\Framework\Routing\Route $route): string => $route->path(),
            $group->localized()->routes(),
        );

        self::assertSame(['/{locale}', '/{locale}/contact'], $paths);
    }

    public function testMiddlewareAppliedToLocalizedSubsetDoesNotAffectPlainRoutes(): void
    {
        $router = new Router();
        $group = $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('home.index', '', 'HomeController@index');
        });

        $group->localized()->middleware(\Lemonade\Framework\Security\Csrf\CsrfMiddleware::class);

        self::assertSame([], $group->plain()->routes()[0]->middlewareStack());
        self::assertSame(
            [\Lemonade\Framework\Security\Csrf\CsrfMiddleware::class],
            $group->localized()->routes()[0]->middlewareStack(),
        );
    }

    public function testMiddlewareAppliedToWholeLocalizedGroupAffectsBothSubsets(): void
    {
        $router = new Router();
        $group = $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('home.index', '', 'HomeController@index');
        });

        $group->middleware(\Lemonade\Framework\Security\Csrf\CsrfMiddleware::class);

        self::assertSame(
            [\Lemonade\Framework\Security\Csrf\CsrfMiddleware::class],
            $group->plain()->routes()[0]->middlewareStack(),
        );
        self::assertSame(
            [\Lemonade\Framework\Security\Csrf\CsrfMiddleware::class],
            $group->localized()->routes()[0]->middlewareStack(),
        );
    }

    public function testLocalizedGroupKeepsPlainAndLocalizedRouteNamesUnchanged(): void
    {
        $router = new Router();
        $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('home.index', '', 'HomeController@index');
        });

        self::assertSame('/', $router->url('home.index'));
        self::assertSame('/cs', $router->url('localized.home.index', ['locale' => 'cs']));
    }

    public function testLocalizedGroupWithSupportedLocalesDoesNotMatchUnsupportedLocale(): void
    {
        $router = new Router();
        $router->configureLocalizedRoutes(supportedLocales: ['cs', 'en', 'de']);
        $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('home.index', '', 'HomeController@index');
            $router->getNamed('contact.index', '/contact', 'ContactController@index');
        });

        $this->expectException(RouteNotFoundException::class);
        $router->match(new ServerRequest('GET', '/dsadsa'));
    }

    public function testLocalizedGroupWithSupportedLocalesDoesNotMatchUnsupportedNestedLocaleRoute(): void
    {
        $router = new Router();
        $router->configureLocalizedRoutes(supportedLocales: ['cs', 'en', 'de']);
        $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('contact.index', '/contact', 'ContactController@index');
        });

        $this->expectException(RouteNotFoundException::class);
        $router->match(new ServerRequest('GET', '/dsadsa/contact'));
    }

    public function testLocalizedGroupWithSupportedLocalesStillMatchesSupportedLocaleRoutes(): void
    {
        $router = new Router();
        $router->configureLocalizedRoutes(supportedLocales: ['cs', 'en', 'de']);
        $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('home.index', '', 'HomeController@index');
            $router->getNamed('contact.index', '/contact', 'ContactController@index');
        });

        $home = $router->match(new ServerRequest('GET', '/en'));
        $contact = $router->match(new ServerRequest('GET', '/en/contact'));

        self::assertSame(['locale' => 'en'], $home->params());
        self::assertSame(['locale' => 'en'], $contact->params());
    }

    public function testAllowedMethodsForPathIgnoresUnsupportedLocalizedPrefix(): void
    {
        $router = new Router();
        $router->configureLocalizedRoutes(supportedLocales: ['cs', 'en']);
        $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('contact.index', '/contact', 'ContactController@index');
        });

        self::assertSame([], $router->allowedMethodsForPath('/dsadsa/contact'));
        self::assertSame(['GET', 'HEAD', 'OPTIONS'], $router->allowedMethodsForPath('/en/contact'));
    }

    public function testLocalizedGroupRespectsCustomLocalizedRouteNamePrefix(): void
    {
        $router = new Router();
        $router->configureLocalizedRoutes(routeNamePrefix: 'i18n.');
        $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('home.index', '', 'HomeController@index');
        });

        self::assertSame('/cs', $router->url('i18n.home.index', ['locale' => 'cs']));
    }

    public function testLocalizedGroupRespectsCustomLocaleParameterName(): void
    {
        $router = new Router();
        $router->configureLocalizedRoutes(localeParameter: 'lang');
        $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('home.index', '', 'HomeController@index');
        });

        self::assertSame('/cs', $router->url('localized.home.index', ['lang' => 'cs']));
    }

    public function testLocalizedGroupSupportsCustomLocalizedPrefixHappyPath(): void
    {
        $router = new Router();
        $router->configureLocalizedRoutes(
            routeNamePrefix: 'i18n.',
            routePrefix: '/content/{lang}',
            localeParameter: 'lang',
            supportedLocales: ['cs', 'en'],
        );

        $group = $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('docs.show', '/docs/{slug}', 'DocsController@show');
        });

        self::assertSame(['/docs/{slug}'], array_map(
            static fn(\Lemonade\Framework\Routing\Route $route): string => $route->path(),
            $group->plain()->routes(),
        ));
        self::assertSame(['/content/{lang}/docs/{slug}'], array_map(
            static fn(\Lemonade\Framework\Routing\Route $route): string => $route->path(),
            $group->localized()->routes(),
        ));

        self::assertSame('/docs/intro', $router->url('docs.show', ['slug' => 'intro']));
        self::assertSame('/content/cs/docs/intro', $router->url('i18n.docs.show', ['lang' => 'cs', 'slug' => 'intro']));

        $match = $router->match(new ServerRequest('GET', '/content/cs/docs/intro'));

        self::assertSame('App\\Controllers\\DocsController', $match->controller());
        self::assertSame('show', $match->action());
        self::assertSame(['lang' => 'cs', 'slug' => 'intro'], $match->params());
    }

    public function testConfigureLocalizedRoutesAcceptsExactCustomLocalePlaceholder(): void
    {
        $router = new Router();
        $router->configureLocalizedRoutes(
            routePrefix: '/content/{lang}',
            localeParameter: 'lang',
        );

        $router->localizedGroup(static function (Router $router): void {
            $router->getNamed('docs.show', '/docs/{slug}', 'DocsController@show');
        });

        self::assertSame(
            '/content/cs/docs/intro',
            $router->url('localized.docs.show', ['lang' => 'cs', 'slug' => 'intro']),
        );
    }

    public function testConfigureLocalizedRoutesRejectsCustomPrefixMissingOpeningBraceInLocalePlaceholder(): void
    {
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);
        $router->configureLocalizedRoutes(
            routePrefix: '/content/lang}',
            localeParameter: 'lang',
        );
    }

    public function testConfigureLocalizedRoutesRejectsCustomPrefixMissingClosingBraceInLocalePlaceholder(): void
    {
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);
        $router->configureLocalizedRoutes(
            routePrefix: '/content/{lang',
            localeParameter: 'lang',
        );
    }

    public function testLocalizedGroupThrowsWhenRoutePrefixDoesNotContainConfiguredLocaleParameter(): void
    {
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);
        $router->configureLocalizedRoutes(routePrefix: '/{locale}', localeParameter: 'lang');
    }

    public function testRegularGroupDoesNotCreateLocalizedVariant(): void
    {
        $router = new Router();
        $router->group('/admin', static function (Router $router): void {
            $router->getNamed('admin.dashboard', '', 'AdminDashboardController@index');
        });

        self::assertSame('/admin', $router->url('admin.dashboard'));
        $this->expectException(RouteNotFoundException::class);
        $router->url('localized.admin.dashboard', ['locale' => 'cs']);
    }

    public function testMapNamedIsPublicReturnsNamedRouteAndSupportsUrlGeneration(): void
    {
        $router = new Router();
        $route = $router->mapNamed('users.show', HttpMethod::GET, '/users/{id}', 'UserController@show');

        self::assertSame('GET', $route->method());
        self::assertSame('users.show', $route->name());
        self::assertSame('/users/15', $router->url('users.show', ['id' => 15]));
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

    public function testMatchDecodesSimplePathParameterRoundTripRegressionCases(): void
    {
        $router = new Router();
        $router->getNamed('items.show', '/items/{value}', 'ItemController@show');

        $cases = [
            '@' => '/items/%40',
            ' ' => '/items/%20',
            'č' => '/items/%C4%8D',
            '%' => '/items/%25',
            '+' => '/items/%2B',
            '%25' => '/items/%2525',
        ];

        foreach ($cases as $expected => $url) {
            self::assertSame($url, $router->url('items.show', ['value' => $expected]));

            $match = $router->match(new ServerRequest('GET', $url));

            self::assertSame(['value' => $expected], $match->params());
        }
    }

    public function testSimpleRouteParameterRoundTripRegressionCasesWithContractA(): void
    {
        $router = new Router();
        $router->getNamed('items.show', '/items/{value}', 'ItemController@show');

        $cases = [
            'a/b' => '/items/a%2Fb',
            '/' => '/items/%2F',
            'a%2Fb' => '/items/a%252Fb',
            '+' => '/items/%2B',
            "\u{010D}/a" => '/items/%C4%8D%2Fa',
        ];

        foreach ($cases as $input => $expectedUrl) {
            $url = $router->url('items.show', ['value' => $input]);
            self::assertSame($expectedUrl, $url);

            $request = new ServerRequest('GET', $url);
            self::assertSame($expectedUrl, $request->getUri()->getPath());

            $match = $router->match($request);

            self::assertSame(['value' => $input], $match->params());
        }
    }

    public function testSimpleRouteParameterUrlGenerationRejectsEmptyString(): void
    {
        $router = new Router();
        $router->getNamed('items.show', '/items/{value}', 'ItemController@show');

        $this->expectException(InvalidArgumentException::class);
        $router->url('items.show', ['value' => '']);
    }

    public function testMatchDecodesEncodedSlashWithoutChangingSegmentBoundaries(): void
    {
        $router = new Router();
        $router->get('/items/{value}/meta', 'ItemController@meta');

        $match = $router->match(new ServerRequest('GET', '/items/%2F/meta'));

        self::assertSame('App\\Controllers\\ItemController', $match->controller());
        self::assertSame('meta', $match->action());
        self::assertSame(['value' => '/'], $match->params());
    }

    public function testMatchExtractsWildcardParameter(): void
    {
        $router = new Router();
        $router->get('/docs/{slug:any}', 'DocsController@show');

        $match = $router->match(new ServerRequest('GET', '/docs/guides/install/windows'));

        self::assertSame(['slug' => 'guides/install/windows'], $match->params());
    }

    public function testMatchWildcardParameterStillCapturesMultipleDecodedSegments(): void
    {
        $router = new Router();
        $router->get('/docs/{slug:any}/edit', 'DocsController@edit');

        $match = $router->match(new ServerRequest('GET', '/docs/guides/%C4%8Desk%C3%BD/%2F/edit'));

        self::assertSame('App\\Controllers\\DocsController', $match->controller());
        self::assertSame('edit', $match->action());
        self::assertSame(['slug' => 'guides/český//'], $match->params());
    }

    public function testWildcardRouteParameterRoundTripRegressionCasesWithContractA(): void
    {
        $router = new Router();
        $router->getNamed('items.show', '/items/{value:any}', 'ItemController@show');

        $cases = [
            'a/b' => '/items/a/b',
            "a/\u{010D}/b" => '/items/a/%C4%8D/b',
            'a%2Fb' => '/items/a%252Fb',
        ];

        foreach ($cases as $input => $expectedUrl) {
            $url = $router->url('items.show', ['value' => $input]);
            self::assertSame($expectedUrl, $url);

            $request = new ServerRequest('GET', $url);
            self::assertSame($expectedUrl, $request->getUri()->getPath());

            $match = $router->match($request);

            self::assertSame(['value' => $input], $match->params());
        }
    }

    public function testWildcardRouteParameterUrlGenerationRejectsEmptyString(): void
    {
        $router = new Router();
        $router->getNamed('items.show', '/items/{value:any}', 'ItemController@show');

        $this->expectException(InvalidArgumentException::class);
        $router->url('items.show', ['value' => '']);
    }

    public function testWildcardRouteParameterUrlGenerationRejectsSlashOnlyValue(): void
    {
        $router = new Router();
        $router->getNamed('items.show', '/items/{value:any}', 'ItemController@show');

        $this->expectException(InvalidArgumentException::class);
        $router->url('items.show', ['value' => '/']);
    }

    public function testWildcardRouteParameterUrlGenerationRejectsLeadingSlash(): void
    {
        $router = new Router();
        $router->getNamed('items.show', '/items/{value:any}', 'ItemController@show');

        $this->expectException(InvalidArgumentException::class);
        $router->url('items.show', ['value' => '/a']);
    }

    public function testWildcardRouteParameterUrlGenerationRejectsTrailingSlash(): void
    {
        $router = new Router();
        $router->getNamed('items.show', '/items/{value:any}', 'ItemController@show');

        $this->expectException(InvalidArgumentException::class);
        $router->url('items.show', ['value' => 'a/']);
    }

    public function testWildcardRouteParameterUrlGenerationRejectsEmptyIntermediateSegment(): void
    {
        $router = new Router();
        $router->getNamed('items.show', '/items/{value:any}', 'ItemController@show');

        $this->expectException(InvalidArgumentException::class);
        $router->url('items.show', ['value' => 'a//b']);
    }

    public function testWildcardRouteParameterUrlGenerationRejectsDoubleSlashOnlyValue(): void
    {
        $router = new Router();
        $router->getNamed('items.show', '/items/{value:any}', 'ItemController@show');

        $this->expectException(InvalidArgumentException::class);
        $router->url('items.show', ['value' => '//']);
    }

    public function testMatchStaticLiteralRouteMatchesEquivalentEncodedRequestPath(): void
    {
        $cases = [
            '/č' => '/%C4%8D',
            '/hello world' => '/hello%20world',
            '/🙂' => '/%F0%9F%99%82',
            '/@' => '/@',
        ];

        foreach ($cases as $registeredPath => $requestPath) {
            $router = new Router();
            $router->get($registeredPath, 'CatalogController@index');

            $match = $router->match(new ServerRequest('GET', $requestPath));

            self::assertSame('App\\Controllers\\CatalogController', $match->controller());
            self::assertSame('index', $match->action());
            self::assertSame([], $match->params());
        }
    }

    public function testMatchThrowsRouteNotFoundExceptionWhenNoRouteMatches(): void
    {
        $router = new Router();
        $router->get('/users', 'UserController@index');

        $this->expectException(RouteNotFoundException::class);
        $router->match(new ServerRequest('GET', '/missing'));
    }

    public function testMatchHeadFindsExplicitHeadRoute(): void
    {
        $router = new Router();
        $router->head('/users', 'HeadUsersController@index');

        $match = $router->match(new ServerRequest('HEAD', '/users'));

        self::assertSame('App\\Controllers\\HeadUsersController', $match->controller());
        self::assertSame('index', $match->action());
    }

    public function testMatchHeadFallsBackToGetRoute(): void
    {
        $router = new Router();
        $router->get('/users', 'UserController@index');

        $match = $router->match(new ServerRequest('HEAD', '/users'));

        self::assertSame('App\\Controllers\\UserController', $match->controller());
        self::assertSame('index', $match->action());
    }

    public function testMatchHeadExplicitRouteHasPriorityOverGetFallback(): void
    {
        $router = new Router();
        $router->get('/users', 'GetUsersController@index');
        $router->head('/users', 'HeadUsersController@index');

        $match = $router->match(new ServerRequest('HEAD', '/users'));

        self::assertSame('App\\Controllers\\HeadUsersController', $match->controller());
        self::assertSame('index', $match->action());
    }

    public function testMatchHeadFallbackWorksForParameterizedGetRoute(): void
    {
        $router = new Router();
        $router->get('/users/{id}', 'UserController@show');

        $match = $router->match(new ServerRequest('HEAD', '/users/99'));

        self::assertSame('App\\Controllers\\UserController', $match->controller());
        self::assertSame('show', $match->action());
        self::assertSame(['id' => '99'], $match->params());
    }

    public function testMatchHeadUsesSameConventionRoutingAsGet(): void
    {
        $router = new Router();
        $router->setControllerNamespace('Lemonade\\Framework\\Tests\\Unit\\Routing');

        $head = $router->match(new ServerRequest('HEAD', '/home'));
        $get = $router->match(new ServerRequest('GET', '/home'));

        self::assertSame($get->controller(), $head->controller());
        self::assertSame($get->action(), $head->action());
        self::assertSame($get->params(), $head->params());
    }

    public function testConventionRoutingResolvesTwoSegmentControllerActionPath(): void
    {
        $router = new Router();
        $router->setControllerNamespace('Lemonade\\Framework\\Tests\\Unit\\Routing\\Convention');

        $match = $router->match(new ServerRequest('GET', '/users/show'));

        self::assertSame('Lemonade\\Framework\\Tests\\Unit\\Routing\\Convention\\UsersController', $match->controller());
        self::assertSame('show', $match->action());
        self::assertSame([], $match->params());
    }

    public function testConventionRoutingResolvesHyphenatedControllerSegment(): void
    {
        $router = new Router();
        $router->setControllerNamespace('Lemonade\\Framework\\Tests\\Unit\\Routing\\Convention');

        $match = $router->match(new ServerRequest('GET', '/admin-users/show'));

        self::assertSame('Lemonade\\Framework\\Tests\\Unit\\Routing\\Convention\\AdminUsersController', $match->controller());
        self::assertSame('show', $match->action());
    }

    public function testConventionRoutingResolvesUnderscoreControllerSegment(): void
    {
        $router = new Router();
        $router->setControllerNamespace('Lemonade\\Framework\\Tests\\Unit\\Routing\\Convention');

        $match = $router->match(new ServerRequest('GET', '/admin_users/show'));

        self::assertSame('Lemonade\\Framework\\Tests\\Unit\\Routing\\Convention\\AdminUsersController', $match->controller());
        self::assertSame('show', $match->action());
    }

    public function testConventionRoutingResolvesNestedControllerPath(): void
    {
        $router = new Router();
        $router->setControllerNamespace('Lemonade\\Framework\\Tests\\Unit\\Routing\\Convention');

        $match = $router->match(new ServerRequest('GET', '/backoffice/users/show'));

        self::assertSame('Lemonade\\Framework\\Tests\\Unit\\Routing\\Convention\\Backoffice\\UsersController', $match->controller());
        self::assertSame('show', $match->action());
    }

    public function testAllowedMethodsForPathIncludesHeadAndOptionsForGetRoute(): void
    {
        $router = new Router();
        $router->get('/users', 'UserController@index');

        self::assertSame(['GET', 'HEAD', 'OPTIONS'], $router->allowedMethodsForPath('/users'));
    }

    public function testAllowedMethodsForPathIncludesOptionsForPostOnlyRoute(): void
    {
        $router = new Router();
        $router->post('/users', 'UserController@store');

        self::assertSame(['POST', 'OPTIONS'], $router->allowedMethodsForPath('/users'));
    }

    public function testAllowedMethodsForPathSupportsParameterizedRoute(): void
    {
        $router = new Router();
        $router->patch('/users/{id}', 'UserController@update');

        self::assertSame(['PATCH', 'OPTIONS'], $router->allowedMethodsForPath('/users/42'));
    }

    public function testAllowedMethodsForPathSupportsWildcardParameterizedRoute(): void
    {
        $router = new Router();
        $router->get('/docs/{slug:any}', 'DocsController@show');

        self::assertSame(['GET', 'HEAD', 'OPTIONS'], $router->allowedMethodsForPath('/docs/guides/install/windows'));
    }

    public function testAllowedMethodsForPathReturnsGetHeadPostOptionsForGetAndPostRoute(): void
    {
        $router = new Router();
        $router->get('/users', 'UserController@index');
        $router->post('/users', 'UserController@store');

        self::assertSame(['GET', 'HEAD', 'POST', 'OPTIONS'], $router->allowedMethodsForPath('/users'));
    }

    public function testAllowedMethodsForPathDoesNotReturnDuplicateMethodsWhenMultipleRoutesMatchSamePath(): void
    {
        $router = new Router();
        $router->get('/docs/{slug}', 'DocsController@show');
        $router->get('/docs/{slug:any}', 'DocsController@showNested');
        $router->post('/docs/{slug:any}', 'DocsController@storeNested');

        self::assertSame(['GET', 'HEAD', 'POST', 'OPTIONS'], $router->allowedMethodsForPath('/docs/intro'));
    }

    public function testAllowedMethodsForPathDoesNotReturnDuplicateHeadWhenGetAndHeadRoutesMatchSamePath(): void
    {
        $router = new Router();
        $router->get('/docs/{slug}', 'DocsController@show');
        $router->head('/docs/{slug:any}', 'DocsController@headNested');

        self::assertSame(['GET', 'HEAD', 'OPTIONS'], $router->allowedMethodsForPath('/docs/intro'));
    }

    public function testAllowedMethodsForPathReturnsEmptyForMissingPath(): void
    {
        $router = new Router();
        $router->get('/users', 'UserController@index');

        self::assertSame([], $router->allowedMethodsForPath('/missing'));
    }

    public function testMatchFindsExplicitOptionsRoute(): void
    {
        $router = new Router();
        $router->options('/users', 'UserController@options');

        $match = $router->match(new ServerRequest('OPTIONS', '/users'));

        self::assertSame('App\\Controllers\\UserController', $match->controller());
        self::assertSame('options', $match->action());
    }

    public function testHasExplicitRouteForPathWorksForExactRoute(): void
    {
        $router = new Router();
        $router->post('/users', 'UserController@store');

        self::assertTrue($router->hasExplicitRouteForPath('POST', '/users'));
        self::assertFalse($router->hasExplicitRouteForPath('GET', '/users'));
    }

    public function testHasExplicitRouteForPathWorksForParameterizedRoute(): void
    {
        $router = new Router();
        $router->get('/users/{id}', 'UserController@show');

        self::assertTrue($router->hasExplicitRouteForPath('GET', '/users/42'));
        self::assertFalse($router->hasExplicitRouteForPath('GET', '/users'));
    }

    public function testConventionRouteIsNotUsedForPost(): void
    {
        $router = new Router();
        $router->setControllerNamespace('Lemonade\\Framework\\Tests\\Unit\\Routing');

        $this->expectException(RouteNotFoundException::class);
        $router->match(new ServerRequest('POST', '/home'));
    }

    public function testConventionRouteIsNotUsedForOptions(): void
    {
        $router = new Router();
        $router->setControllerNamespace('Lemonade\\Framework\\Tests\\Unit\\Routing');

        $this->expectException(RouteNotFoundException::class);
        $router->match(new ServerRequest('OPTIONS', '/home'));
    }

    public function testAllowedMethodsForPathIncludesConventionGetHeadOptions(): void
    {
        $router = new Router();
        $router->setControllerNamespace('Lemonade\\Framework\\Tests\\Unit\\Routing');

        self::assertSame(['GET', 'HEAD', 'OPTIONS'], $router->allowedMethodsForPath('/home'));
    }
}

final class HomeController {}
