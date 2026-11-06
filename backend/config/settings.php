<?php

declare(strict_types=1);

use App\Support\Env;

return [
    'settings' => [
        'displayErrorDetails' => Env::bool('APP_DEBUG', false),
        'logger' => [
            'name' => 'products-import',
            'path' => __DIR__ . '/../storage/logs/app.log',
            'level' => Env::string('LOG_LEVEL', 'info'),
        ],
        'db' => [
            'url' => Env::string('DATABASE_URL', 'sqlite:///' . __DIR__ . '/../storage/app.db'),
        ],
        'jwt' => [
            'secret' => Env::string('JWT_SECRET', 'dev-secret-change-me-0123456789abcdef'),
            'ttl' => Env::int('JWT_TTL', 3600),
        ],
        'messenger' => [
            'transport_dsn' => Env::string('MESSENGER_TRANSPORT_DSN', 'sync://'),
        ],
        'images' => [
            'storage_path' => Env::string('IMAGE_STORAGE_PATH', __DIR__ . '/../storage'),
            'max_bytes' => Env::int('IMAGE_MAX_BYTES', 10 * 1024 * 1024),
            'timeout' => Env::int('IMAGE_DOWNLOAD_TIMEOUT', 15),
        ],
        'upload' => [
            'max_bytes' => Env::int('UPLOAD_MAX_BYTES', 20 * 1024 * 1024),
            'allowed_extensions' => ['xlsx'],
            'allowed_mime_types' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/octet-stream',
            ],
        ],
        'rate_limit' => [
            'max_requests' => Env::int('RATE_LIMIT_MAX_REQUESTS', 5),
            'period_seconds' => Env::int('RATE_LIMIT_PERIOD_SECONDS', 60),
        ],
        'admin' => [
            'email' => Env::string('ADMIN_EMAIL', 'admin@example.com'),
            'password' => Env::string('ADMIN_PASSWORD', 'admin123'),
            'name' => Env::string('ADMIN_NAME', 'Администратор'),
        ],
    ],
];
