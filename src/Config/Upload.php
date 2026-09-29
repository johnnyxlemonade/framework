<?php

declare(strict_types=1);

use Lemonade\Framework\Upload\Config\UploadConfigDefinition;

return UploadConfigDefinition::create()
    ->fileProfile(
        profile: 'default',
        targetDirectory: 'files',
        maxBytes: 2 * 1024 * 1024,
        allowedExtensions: ['pdf', 'doc', 'docx', 'txt'],
    )
    ->imageProfile(
        profile: 'default',
        targetDirectory: 'images',
        maxBytes: 2 * 1024 * 1024,
        allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
        reencode: true,
    );
