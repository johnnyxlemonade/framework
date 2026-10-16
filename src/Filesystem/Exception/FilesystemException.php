<?php

declare(strict_types=1);

namespace Lemonade\Framework\Filesystem\Exception;

use RuntimeException;

/**
 * Identifies a filesystem boundary failure and retains its operation category through stable error codes.
 */
final class FilesystemException extends RuntimeException
{
    // Directory operations
    public const int CODE_DIR_CREATE_FAILED = 101;
    public const int CODE_DIR_DELETE_FAILED = 102;
    public const int CODE_PATH_NOT_FOUND = 103;
    public const int CODE_PATH_NOT_A_DIRECTORY = 104;
    public const int CODE_FIND_PATTERN_INVALID = 105;

    // File operations
    public const int CODE_FILE_WRITE_FAILED = 201;
    public const int CODE_FILE_READ_FAILED = 202;
    public const int CODE_FILE_DELETE_FAILED = 203;
    public const int CODE_FILE_NOT_FOUND = 204;
    public const int CODE_FILE_NOT_A_FILE = 205;
    public const int CODE_FILE_CONFLICT = 206;
    public const int CODE_COPY_FAILED = 207;
    public const int CODE_FILE_MOVE_FAILED = 208;
    public const int CODE_HASH_ALGORITHM_INVALID = 209;

    // Access
    public const int CODE_NOT_READABLE = 301;

    // Locking
    public const int CODE_LOCK_FAILED = 401;
}
