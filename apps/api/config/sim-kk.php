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
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    ],
];
