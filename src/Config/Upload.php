<?php

declare(strict_types=1);

return [
    'profiles' => [
        'file' => [
            'max_size' => 2 * 1024 * 1024,
            'allowed_mime_types' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
            ],
            'target_dir' => 'storage/uploads/files',
        ],
        'image' => [
            'max_size' => 2 * 1024 * 1024,
            'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            'target_dir' => 'storage/uploads/images',
        ],
    ],
];
