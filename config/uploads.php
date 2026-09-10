<?php

return [
    'private_uploads' => (bool) env('SWGPI_PRIVATE_UPLOADS', false),
    'x_accel_downloads' => (bool) env('SWGPI_X_ACCEL_DOWNLOADS', false),
    'legacy_public_fallback' => (bool) env('SWGPI_LEGACY_PUBLIC_FALLBACK', true),

    'temporary_disk' => env('SWGPI_TEMPORARY_DISK', 'local'),
    'private_disk' => 'swgpi_private',
    'public_disk' => 'swgpi_public',
    'legacy_disk' => 'legacy_public',
    'write_disk' => env('SWGPI_PRIVATE_UPLOADS', false) ? 'swgpi_private' : 'legacy_public',

    'temporary_directory' => '.temporary/uploads',
    'x_accel_prefix' => '/_swgpi_private',
    'max_size_mb' => (int) env('SWGPI_MAX_UPLOAD_MB', 50),

    'default_extensions' => [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip',
        'txt', 'jpg', 'jpeg', 'png', 'webp', 'epub', 'rar', '7z',
    ],

    'executable_extensions' => [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'dll', 'com', 'bat', 'cmd',
        'msi', 'js', 'mjs', 'html', 'htm', 'svg', 'jar', 'scr', 'vbs',
    ],
];
