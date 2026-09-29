<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Core\CoreServiceProvider;
use Lemonade\Framework\Http\Response\Responses;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class CoreServiceProviderResponsesTest extends TestCase
{
    public function testRegistersSingletonResponsesForPlainControllerConstructorInjection(): void
    {
        $container = new Container();
        $container->singleton(AppConfig::class, new AppConfig(null, null, '', '', 'testing', false, '', '', ''));
        (new CoreServiceProvider())->register($container);

        self::assertTrue($container->isBound(Responses::class));
        self::assertSame($container->get(Responses::class), $container->get(Responses::class));

        $controller = $container->get(ResponsesInjectionController::class);

        $json = $controller->json();
        self::assertSame('application/json; charset=UTF-8', $json->getHeaderLine('Content-Type'));
        self::assertSame('{"ok":true}', (string) $json->getBody());

        $text = $controller->text();
        self::assertSame('plain response', (string) $text->getBody());

        $redirect = $controller->redirect();
        self::assertSame('/next', $redirect->getHeaderLine('Location'));
    }
}

final class ResponsesInjectionController
{
    public function __construct(
        private readonly Responses $responses,
    ) {}

    public function json(): ResponseInterface
    {
        return $this->responses->json(['ok' => true]);
    }

    public function text(): ResponseInterface
    {
        return $this->responses->text('plain response');
    }

    public function redirect(): ResponseInterface
    {
        return $this->responses->redirect('/next');
    }
}
