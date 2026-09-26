<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http;

/**
 * General HTTP response status codes used by the framework.
 *
 * PSR-7 response factories continue to receive the backed integer value.
 */
enum HttpStatus: int
{
    case OK = 200;
    case CREATED = 201;
    case ACCEPTED = 202;
    case NO_CONTENT = 204;
    case MULTIPLE_CHOICES = 300;
    case MOVED_PERMANENTLY = 301;
    case FOUND = 302;
    case SEE_OTHER = 303;
    case NOT_MODIFIED = 304;
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case METHOD_NOT_ALLOWED = 405;
    case CONFLICT = 409;
    case PAYLOAD_TOO_LARGE = 413;
    case UNSUPPORTED_MEDIA_TYPE = 415;
    case CSRF_TOKEN_MISMATCH = 419;
    case UNPROCESSABLE_ENTITY = 422;
    case TOO_MANY_REQUESTS = 429;
    case INTERNAL_SERVER_ERROR = 500;
    case SERVICE_UNAVAILABLE = 503;
}
