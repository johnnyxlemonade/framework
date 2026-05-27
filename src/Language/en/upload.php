<?php

declare(strict_types=1);

return [
    'payload_missing' => 'The uploaded file payload is missing.',
    'file_empty' => 'The uploaded file is empty.',
    'file_too_large' => 'The uploaded file exceeds the maximum allowed size.',
    'mime_not_detected' => 'Could not detect the MIME type of the uploaded file.',
    'mime_not_allowed' => 'MIME type "{mime}" is not allowed.',
    'extension_not_allowed' => 'File extension "{extension}" is not allowed.',
    'tmp_not_valid' => 'The temporary uploaded file is not valid.',

    'error_too_large' => 'The uploaded file is too large.',
    'error_partial' => 'The file was only partially uploaded.',
    'error_no_file' => 'No file was uploaded.',
    'error_no_tmp_dir' => 'The temporary upload directory is missing.',
    'error_cant_write' => 'The file could not be written to disk.',
    'error_stopped_by_extension' => 'The file upload was stopped by a PHP extension.',
    'error_unknown' => 'Unknown upload error.',

    'target_directory_missing' => 'The upload target directory is not configured.',
    'create_target_directory_failed' => 'Could not create the target directory: {directory}',
    'move_failed' => 'The uploaded file could not be moved.',
    'file_size_not_detected' => 'Could not detect the size of the stored file.',
    'filename_generation_failed' => 'Could not generate the stored filename.',

    'image_not_valid' => 'The uploaded file is not a valid image.',
    'image_min_width' => 'The image width must be at least {width} px.',
    'image_max_width' => 'The image width must be at most {width} px.',
    'image_min_height' => 'The image height must be at least {height} px.',
    'image_max_height' => 'The image height must be at most {height} px.',
    'image_mime_not_supported' => 'Image MIME type "{mime}" is not supported.',
    'gd_not_available' => 'The image cannot be processed safely because the GD extension is not available.',
    'stored_image_not_readable' => 'The stored file is not a readable image.',
    'image_decode_failed' => 'The uploaded image could not be decoded.',
    'image_reencode_failed' => 'The re-encoded image could not be saved.',

    'file_profile_not_configured' => 'File upload profile "{profile}" is not configured.',
    'file_profile_missing_target_directory' => 'File upload profile "{profile}" does not have target_directory configured.',
    'image_profile_not_configured' => 'Image upload profile "{profile}" is not configured.',
    'image_profile_missing_target_directory' => 'Image upload profile "{profile}" does not have target_directory configured.',
];
