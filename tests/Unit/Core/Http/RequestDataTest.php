<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Http;

use Lemonade\Framework\Core\Http\RequestData;
use Lemonade\Framework\Http\Request\HttpMethod;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;

final class RequestDataTest extends TestCase
{
    public function testInputPrefersParsedBodyOverQueryParams(): void
    {
        $request = $this->request('POST')
            ->withParsedBody(['key' => 'body-value'])
            ->withQueryParams(['key' => 'query-value']);

        $data = new RequestData($request);

        self::assertSame('body-value', $data->input('key'));
    }

    public function testInputUsesQueryParamsWhenParsedBodyMissesKey(): void
    {
        $request = $this->request('GET')
            ->withParsedBody(['other' => 'x'])
            ->withQueryParams(['key' => 'query-value']);

        $data = new RequestData($request);

        self::assertSame('query-value', $data->input('key'));
    }

    public function testInputUsesJsonPayloadWhenBodyAndQueryMiss(): void
    {
        $request = $this->request('POST', '{"key":"json-value"}')
            ->withParsedBody(['other' => 'x'])
            ->withQueryParams(['another' => 'y']);

        $data = new RequestData($request);

        self::assertSame('json-value', $data->input('key'));
    }

    public function testInputReturnsDefaultWhenMissing(): void
    {
        $data = new RequestData($this->request('GET'));

        self::assertSame('default', $data->input('missing', 'default'));
    }

    public function testQueryAndPostAccessorsReturnExpectedData(): void
    {
        $request = $this->request('POST')
            ->withQueryParams(['q' => 'search'])
            ->withParsedBody(['title' => 'hello']);

        $data = new RequestData($request);

        self::assertSame('search', $data->query('q'));
        self::assertSame(['q' => 'search'], $data->queryAll());
        self::assertSame('hello', $data->post('title'));
        self::assertSame(['title' => 'hello'], $data->postAll());
    }

    public function testHeaderReturnsDefaultWhenMissing(): void
    {
        $data = new RequestData($this->request('GET'));

        self::assertSame('fallback', $data->header('X-Missing', 'fallback'));
    }

    public function testCookieCookiesServerServerAllUsePsrRequestData(): void
    {
        $request = $this->request('GET')
            ->withCookieParams(['sid' => 'abc', 'theme' => 'dark'])
            ->withQueryParams(['ignored' => 'x']);

        $request = $request->withAttribute('noop', 'noop');
        $request = $request->withHeader('X-Test', 'ok');
        $request = $request->withParsedBody(['post' => 'v']);
        $request = $request->withProtocolVersion('1.1');

        $serverParams = [
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'example.test',
        ];
        $request = new ServerRequest(
            method: 'GET',
            uri: '/',
            headers: $request->getHeaders(),
            body: $request->getBody(),
            version: '1.1',
            serverParams: $serverParams,
        );
        $request = $request->withCookieParams(['sid' => 'abc', 'theme' => 'dark']);

        $data = new RequestData($request);

        self::assertSame('abc', $data->cookie('sid'));
        self::assertSame(['sid' => 'abc', 'theme' => 'dark'], $data->cookies());
        self::assertSame('GET', $data->server('REQUEST_METHOD'));
        self::assertSame($serverParams, $data->serverAll());
    }

    public function testJsonPayloadReturnsArrayForValidJsonObject(): void
    {
        $data = new RequestData($this->request('POST', '{"name":"Lemonade","version":1}'));

        self::assertSame(['name' => 'Lemonade', 'version' => 1], $data->jsonPayload());
    }

    public function testJsonPayloadReturnsEmptyArrayForInvalidJson(): void
    {
        $data = new RequestData($this->request('POST', '{"name":'));

        self::assertSame([], $data->jsonPayload());
    }

    public function testIsJsonRequestDetectsApplicationJsonContentType(): void
    {
        $request = $this->request('POST')->withHeader('Content-Type', 'application/json; charset=utf-8');
        $data = new RequestData($request);

        self::assertTrue($data->isJsonRequest());
    }

    public function testAcceptsJsonDetectsJsonAcceptPatterns(): void
    {
        $json = new RequestData($this->request('GET')->withHeader('Accept', 'application/json'));
        $appWildcard = new RequestData($this->request('GET')->withHeader('Accept', 'application/*'));
        $any = new RequestData($this->request('GET')->withHeader('Accept', '*/*'));

        self::assertTrue($json->acceptsJson());
        self::assertTrue($appWildcard->acceptsJson());
        self::assertTrue($any->acceptsJson());
    }

    public function testExpectsJsonForJsonRequestAcceptJsonOrAjaxRequest(): void
    {
        $jsonRequest = new RequestData($this->request('POST')->withHeader('Content-Type', 'application/json'));
        $acceptJson = new RequestData($this->request('GET')->withHeader('Accept', 'application/json'));
        $ajax = new RequestData($this->request('GET')->withHeader('X-Requested-With', 'XMLHttpRequest'));

        self::assertTrue($jsonRequest->expectsJson());
        self::assertTrue($acceptJson->expectsJson());
        self::assertTrue($ajax->expectsJson());
    }

    public function testMethodAndMethodHelpers(): void
    {
        $get = new RequestData($this->request('GET'));
        $post = new RequestData($this->request('POST'));
        $put = new RequestData($this->request('PUT'));
        $patch = new RequestData($this->request('PATCH'));
        $delete = new RequestData($this->request('DELETE'));
        $head = new RequestData($this->request('HEAD'));
        $options = new RequestData($this->request('OPTIONS'));

        self::assertSame('GET', $get->method());
        self::assertTrue($get->isMethod(HttpMethod::GET));
        self::assertTrue($get->isGet());
        self::assertTrue($post->isPost());
        self::assertTrue($put->isPut());
        self::assertTrue($patch->isPatch());
        self::assertTrue($delete->isDelete());
        self::assertTrue($head->isHead());
        self::assertTrue($options->isOptions());
    }

    public function testInputStringReturnsScalarAsStringAndDefaultForArrayObject(): void
    {
        $request = $this->request('POST')
            ->withParsedBody([
                'scalar' => 123,
                'array' => ['x'],
                'object' => new \stdClass(),
            ]);
        $data = new RequestData($request);

        self::assertSame('123', $data->inputString('scalar', 'fallback'));
        self::assertSame('fallback', $data->inputString('array', 'fallback'));
        self::assertSame('fallback', $data->inputString('object', 'fallback'));
    }

    public function testInputIntAcceptsIntIntegerStringAndFloat(): void
    {
        $request = $this->request('POST')
            ->withParsedBody([
                'int' => 5,
                'int_string' => '42',
                'float' => 9.8,
                'invalid' => 'x',
            ]);
        $data = new RequestData($request);

        self::assertSame(5, $data->inputInt('int'));
        self::assertSame(42, $data->inputInt('int_string'));
        self::assertSame(9, $data->inputInt('float'));
        self::assertSame(7, $data->inputInt('invalid', 7));
    }

    public function testInputFloatAcceptsFloatIntAndNumericString(): void
    {
        $request = $this->request('POST')
            ->withParsedBody([
                'float' => 1.5,
                'int' => 4,
                'numeric' => '9.75',
                'invalid' => 'x',
            ]);
        $data = new RequestData($request);

        self::assertSame(1.5, $data->inputFloat('float'));
        self::assertSame(4.0, $data->inputFloat('int'));
        self::assertSame(9.75, $data->inputFloat('numeric'));
        self::assertSame(2.5, $data->inputFloat('invalid', 2.5));
    }

    public function testInputBoolAcceptsSupportedValues(): void
    {
        $request = $this->request('POST')
            ->withParsedBody([
                'true_bool' => true,
                'false_bool' => false,
                'one' => 1,
                'zero' => 0,
                'yes' => 'yes',
                'no' => 'no',
                'on' => 'on',
                'off' => 'off',
                'invalid' => 'maybe',
            ]);
        $data = new RequestData($request);

        self::assertTrue($data->inputBool('true_bool'));
        self::assertFalse($data->inputBool('false_bool'));
        self::assertTrue($data->inputBool('one'));
        self::assertFalse($data->inputBool('zero'));
        self::assertTrue($data->inputBool('yes'));
        self::assertFalse($data->inputBool('no'));
        self::assertTrue($data->inputBool('on'));
        self::assertFalse($data->inputBool('off'));
        self::assertTrue($data->inputBool('invalid', true));
    }

    private function request(string $method, string $body = ''): ServerRequest
    {
        return new ServerRequest($method, '/', [], Stream::create($body));
    }
}
