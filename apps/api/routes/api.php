<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\DailyReportController;
use App\Http\Controllers\Api\InventarisController;
use App\Http\Controllers\Api\InventoryMovementController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\RekamMedisController;
use App\Http\Controllers\Api\TelegramController;
use App\Http\Controllers\Api\TransaksiController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/bootstrap', [BootstrapController::class, 'index']);

    Route::post('/transactions/pay', [TransaksiController::class, 'pay'])
        ->middleware('role:Kasir,Manajer');

    Route::post('/patients/{patient}/treatments', [RekamMedisController::class, 'addTreatment'])
        ->middleware('role:Terapis,Manajer');

    Route::post('/patients/{patient}/photos', [RekamMedisController::class, 'addPhoto'])
        ->middleware('role:Terapis,Manajer');

    Route::post('/inventory/purchases', [InventarisController::class, 'addPurchase'])
        ->middleware('role:Gudang,Manajer');

    Route::get('/reports/{report}/export', [LaporanController::class, 'export'])
        ->middleware('role:Manajer');

    // Daily Report (PRD 3.3.1) — multi-section PDF with dual TTD.
    Route::get('/daily-reports/{tanggal}/export', [DailyReportController::class, 'export'])
        ->middleware('role:Manajer');
    Route::post('/daily-reports/{tanggal}/submit', [DailyReportController::class, 'submit'])
        ->middleware('role:Kasir,Manajer');
    Route::post('/daily-reports/closings/{id}/approve', [DailyReportController::class, 'approve'])
        ->middleware('role:Manajer');

    // Inventory Movements — per barang per hari, 11 columns.
    Route::get('/inventory-movements', [InventoryMovementController::class, 'index'])
        ->middleware('role:Gudang,Manajer');

    Route::post('/telegram/reminder', [TelegramController::class, 'reminder'])
        ->middleware('role:Manajer,Kasir,Terapis');
    Route::post('/telegram/aftercare', [TelegramController::class, 'aftercare'])
        ->middleware('role:Manajer,Terapis');
});

// Telegram webhook — public (Telegram servers call this, no auth)
// Webhook secret verification would be added via X-Telegram-Bot-Api-Secret-Token header in production.
Route::match(['get', 'post'], '/telegram/webhook', [TelegramController::class, 'webhook']);

Route::get('/health', fn () => response()->json(['ok' => true]));
