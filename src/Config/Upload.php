<?php

declare(strict_types=1);

use Lemonade\Framework\Upload\Config\UploadConfigDefinition;

return UploadConfigDefinition::create()
    ->fileProfile(
        profile: 'default',
        targetDirectory: 'files',
        maxBytes: 2 * 1024 * 1024,
        allowedMimeTypes: [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
        ],
    )
    ->imageProfile(
        profile: 'default',
        targetDirectory: 'images',
        maxBytes: 2 * 1024 * 1024,
        allowedMimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        reencode: true,
    );
