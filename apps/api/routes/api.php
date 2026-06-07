<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DailyReportController;
use App\Http\Controllers\Api\InventarisController;
use App\Http\Controllers\Api\InventoryMovementController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\LayananController;
use App\Http\Controllers\Api\ProdukController;
use App\Http\Controllers\Api\RekamMedisController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TelegramController;
use App\Http\Controllers\Api\TransaksiController;
use App\Http\Controllers\Api\UserAdminController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/bootstrap', [BootstrapController::class, 'index']);

    Route::post('/transactions/pay', [TransaksiController::class, 'pay'])
        ->middleware('role:Kasir,Manajer');
    Route::delete('/transactions/{transaksi}', [TransaksiController::class, 'destroy'])
        ->middleware('role:Manajer');

    Route::post('/patients/{patient}/treatments', [RekamMedisController::class, 'addTreatment'])
        ->middleware('role:Terapis,Manajer');

    Route::post('/patients/{patient}/photos', [RekamMedisController::class, 'addPhoto'])
        ->middleware('role:Terapis,Manajer');

    Route::post('/inventory/purchases', [InventarisController::class, 'addPurchase'])
        ->middleware('role:Gudang,Manajer');
    Route::delete('/inventory/purchases/{batch}', [InventarisController::class, 'deleteBatch'])
        ->middleware('role:Gudang,Manajer');

    Route::get('/reports/{report}/export', [LaporanController::class, 'export'])
        ->middleware('role:Manajer');

    // Daily Report (PRD 3.3.1) — multi-section PDF with dual TTD.
    Route::get('/daily-reports/status', [DailyReportController::class, 'status']);
    Route::get('/daily-reports/{tanggal}/export', [DailyReportController::class, 'export'])
        ->middleware('role:Manajer');
    Route::post('/daily-reports/{tanggal}/submit', [DailyReportController::class, 'submit'])
        ->middleware('role:Kasir,Manajer');
    Route::post('/daily-reports/closings/{id}/approve', [DailyReportController::class, 'approve'])
        ->middleware('role:Manajer');

    // Global search (Manajer).
    Route::get('/search', [SearchController::class, 'index'])
        ->middleware('role:Manajer');

    // Manajer dashboard (today stats + last 7 days + top therapists/services).
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('role:Manajer');

    // Audit log viewer (Manajer).
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('role:Manajer');

    // Manual backup trigger (Manajer). Runs .workflow/sim-kk-backup.sh via Symfony Process.
    Route::post('/backup/trigger', [BackupController::class, 'trigger'])
        ->middleware('role:Manajer');

    // Inventory Movements — per barang per hari, 11 columns.
    Route::get('/inventory-movements', [InventoryMovementController::class, 'index'])
        ->middleware('role:Gudang,Manajer');

    // Photo proxy — streams R2 object through Laravel (avoids R2 presigned URL signature quirks).
    // URL: /api/photos/{id}/raw
    Route::get('/photos/{photo}/raw', [RekamMedisController::class, 'streamPhoto']);

    Route::post('/telegram/reminder', [TelegramController::class, 'reminder'])
        ->middleware('role:Manajer,Kasir,Terapis');
    Route::post('/telegram/aftercare', [TelegramController::class, 'aftercare'])
        ->middleware('role:Manajer,Terapis');

    // Admin: Layanan / Produk / User CRUD — Manajer only.
    Route::get('/admin/layanan', [LayananController::class, 'index']);
    Route::post('/admin/layanan', [LayananController::class, 'store']);
    Route::get('/admin/layanan/{layanan}', [LayananController::class, 'show']);
    Route::match(['put', 'patch'], '/admin/layanan/{layanan}', [LayananController::class, 'update']);
    Route::delete('/admin/layanan/{layanan}', [LayananController::class, 'destroy']);

    Route::get('/admin/produk', [ProdukController::class, 'index']);
    Route::post('/admin/produk', [ProdukController::class, 'store']);
    Route::get('/admin/produk/{produk}', [ProdukController::class, 'show']);
    Route::match(['put', 'patch'], '/admin/produk/{produk}', [ProdukController::class, 'update']);
    Route::delete('/admin/produk/{produk}', [ProdukController::class, 'destroy']);

    Route::get('/admin/users', [UserAdminController::class, 'index']);
    Route::post('/admin/users', [UserAdminController::class, 'store']);
    Route::get('/admin/users/{user}', [UserAdminController::class, 'show']);
    Route::match(['put', 'patch'], '/admin/users/{user}', [UserAdminController::class, 'update']);
    Route::delete('/admin/users/{user}', [UserAdminController::class, 'destroy']);
});

// Telegram webhook — public (Telegram servers call this, no auth)
// Webhook secret verification would be added via X-Telegram-Bot-Api-Secret-Token header in production.
Route::match(['get', 'post'], '/telegram/webhook', [TelegramController::class, 'webhook']);

Route::get('/health', fn () => response()->json(['ok' => true]));
