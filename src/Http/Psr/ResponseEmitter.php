<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Psr;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Emits PSR-7 responses by forwarding readable body chunks without materializing the full body
 */
final class ResponseEmitter
{
    private const int READ_CHUNK_SIZE = 65536;

    /**
     * Sends the response status and headers, then emits readable body chunks except for HEAD requests
     */
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

        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            $chunk = $body->read(self::READ_CHUNK_SIZE);

            if ($chunk === '') {
                continue;
            }

            echo $chunk;
        }
    }
}
