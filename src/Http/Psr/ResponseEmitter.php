<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Psr;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ResponseEmitter
{
    public function emit(ResponseInterface $response, ?ServerRequestInterface $request = null): void
    {
        http_response_code($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        if ($request !== null && strtoupper($request->getMethod()) === 'HEAD') {
            return;
        }

        echo (string) $response->getBody();
    }
}
