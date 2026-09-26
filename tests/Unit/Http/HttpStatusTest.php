<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http;

use Lemonade\Framework\Http\HttpStatus;
use PHPUnit\Framework\TestCase;

final class HttpStatusTest extends TestCase
{
    public function testBackedValuesMatchFrameworkHttpStatuses(): void
    {
        self::assertSame([
            'OK' => 200,
            'CREATED' => 201,
            'ACCEPTED' => 202,
            'NO_CONTENT' => 204,
            'MULTIPLE_CHOICES' => 300,
            'MOVED_PERMANENTLY' => 301,
            'FOUND' => 302,
            'SEE_OTHER' => 303,
            'NOT_MODIFIED' => 304,
            'BAD_REQUEST' => 400,
            'UNAUTHORIZED' => 401,
            'FORBIDDEN' => 403,
            'NOT_FOUND' => 404,
            'METHOD_NOT_ALLOWED' => 405,
            'CONFLICT' => 409,
            'PAYLOAD_TOO_LARGE' => 413,
            'UNSUPPORTED_MEDIA_TYPE' => 415,
            'CSRF_TOKEN_MISMATCH' => 419,
            'UNPROCESSABLE_ENTITY' => 422,
            'TOO_MANY_REQUESTS' => 429,
            'INTERNAL_SERVER_ERROR' => 500,
            'SERVICE_UNAVAILABLE' => 503,
        ], array_column(HttpStatus::cases(), 'value', 'name'));
    }
}
