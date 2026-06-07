<?php

return [
    'clinic' => [
        'name'    => env('CLINIC_NAME', 'KLINIK KECANTIKAN SIM-KK'),
        'address' => env('CLINIC_ADDRESS', 'Jl. Operasional Klinik No. 25, Samarinda'),
    ],
    'stock' => [
        'menipis_threshold' => (int) env('STOCK_MENIPIS_THRESHOLD', 12),
        'prioritas_expiry'  => env('STOCK_PRIORITAS_EXPIRY', '2026-07-31'),
    ],
    'gaji_pokok_default' => (int) env('GAJI_POKOK_DEFAULT', 2500000),
    'storage' => [
        'disk' => env('STORAGE_DISK', 'local'),
    ],
    'telegram' => [
        'bot_token'      => env('TELEGRAM_BOT_TOKEN'),
        // Optional secret for X-Telegram-Bot-Api-Secret-Token verification.
        // When set, every POST to /api/telegram/webhook must include the matching header.
        // When null/empty, webhook returns 503 (operator must configure before going live).
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],
    'backup' => [
        'project_root' => env('BACKUP_PROJECT_ROOT', '/var/www/sim-kk'),
    ],
];
