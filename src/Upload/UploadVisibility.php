<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

/**
 * Selects whether a validated upload is published below the web root or retained in private writable storage.
 */
enum UploadVisibility: string
{
    case Public = 'public';
    case Private = 'private';
}
