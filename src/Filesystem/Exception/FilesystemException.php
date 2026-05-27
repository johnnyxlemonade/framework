<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem\Exception;

use RuntimeException;

final class FilesystemException extends RuntimeException
{
    // Directory operations
    public const CODE_DIR_CREATE_FAILED = 101;
    public const CODE_DIR_DELETE_FAILED = 102;

    // File operations
    public const CODE_FILE_WRITE_FAILED = 201;
    public const CODE_FILE_READ_FAILED = 202;
    public const CODE_FILE_DELETE_FAILED = 203;
    public const CODE_FILE_NOT_FOUND = 204;
    public const CODE_FILE_NOT_A_FILE = 205;
    public const CODE_FILE_CONFLICT = 206;
    public const CODE_COPY_FAILED = 207;

    // Permissions and access
    public const CODE_NOT_READABLE = 301;
    public const CODE_NOT_WRITABLE = 302;

    // Locking
    public const CODE_LOCK_FAILED = 401;
}
