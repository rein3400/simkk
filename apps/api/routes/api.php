<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DailyReportController;
use App\Http\Controllers\Api\DeployController;
use App\Http\Controllers\Api\InventarisController;
use App\Http\Controllers\Api\InventoryMovementController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\LayananController;
use App\Http\Controllers\Api\ProdukController;
use App\Http\Controllers\Api\RekamMedisController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\TelegramController;
use App\Http\Controllers\Api\TransaksiController;
use App\Http\Controllers\Api\UserAdminController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:20,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/bootstrap', [BootstrapController::class, 'index']);

    Route::post('/transactions/pay', [TransaksiController::class, 'pay'])
        ->middleware('role:Kasir,Manajer');
    Route::delete('/transactions/{transaksi}', [TransaksiController::class, 'destroy'])
        ->middleware('role:Manajer');

    Route::post('/patients/{patient}/treatments', [RekamMedisController::class, 'addTreatment'])
        ->middleware('role:Terapis,Manajer');
    Route::match(['put', 'patch'], '/patients/{patient}/treatments/{treatment}', [RekamMedisController::class, 'updateTreatment'])
        ->middleware('role:Terapis,Manajer');
    Route::delete('/patients/{patient}/treatments/{treatment}', [RekamMedisController::class, 'deleteTreatment'])
        ->middleware('role:Terapis,Manajer');

    Route::post('/patients/{patient}/photos', [RekamMedisController::class, 'addPhoto'])
        ->middleware('role:Terapis,Manajer');
    // Per revisi "dibuat bisa lebih dari 1 gambar" — batch upload up to 10 photos.
    Route::post('/patients/{patient}/photos/batch', [RekamMedisController::class, 'addPhotos'])
        ->middleware('role:Terapis,Manajer');

    // Per revisi R1/R2 — booking system with time-slot anti-overlap.
    Route::get('/bookings/availability', [BookingController::class, 'availability'])
        ->middleware('role:Kasir,Terapis,Manajer');
    Route::get('/bookings', [BookingController::class, 'index'])
        ->middleware('role:Kasir,Terapis,Manajer');
    Route::post('/bookings', [BookingController::class, 'store'])
        ->middleware('role:Kasir,Terapis,Manajer');
    Route::match(['put', 'patch'], '/bookings/{booking}', [BookingController::class, 'update'])
        ->middleware('role:Kasir,Terapis,Manajer');
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])
        ->middleware('role:Kasir,Terapis,Manajer');
    Route::delete('/patients/{patient}/photos/{photo}', [RekamMedisController::class, 'deletePhoto'])
        ->middleware('role:Terapis,Manajer');

    Route::post('/inventory/purchases', [InventarisController::class, 'addPurchase'])
        ->middleware('role:Gudang,Manajer');
    Route::delete('/inventory/purchases/{batch}', [InventarisController::class, 'deleteBatch'])
        ->middleware('role:Gudang,Manajer');

    Route::get('/reports/{report}/export', [LaporanController::class, 'export'])
        ->middleware('role:Manajer');

    // Daily Report (PRD 3.3.1) — multi-section PDF with dual TTD.
    // Per revisi R12 — status endpoint expose total_komisi, jadi Manajer+Admin only.
    Route::get('/daily-reports/status', [DailyReportController::class, 'status'])
        ->middleware('role:Manajer,Admin');
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

    // Deploy endpoint — Manajer-only, deploy secret required (HTTP-based deploy when SSH is filtered).
    Route::post('/admin/deploy', DeployController::class)
        ->middleware('role:Manajer');

    // Inventory Movements — per barang per hari, 11 columns.
    Route::get('/inventory-movements', [InventoryMovementController::class, 'index'])
        ->middleware('role:Gudang,Manajer');

    Route::post('/telegram/reminder', [TelegramController::class, 'reminder'])
        ->middleware('role:Manajer,Kasir,Terapis');
    Route::post('/telegram/aftercare', [TelegramController::class, 'aftercare'])
        ->middleware('role:Manajer,Terapis');

    // Admin: Layanan / Produk / User CRUD — Manajer only (P0 privilege escalation fix).
    Route::get('/admin/layanan', [LayananController::class, 'index'])
        ->middleware('role:Manajer');
    Route::post('/admin/layanan', [LayananController::class, 'store'])
        ->middleware('role:Manajer');
    Route::get('/admin/layanan/{layanan}', [LayananController::class, 'show'])
        ->middleware('role:Manajer');
    Route::match(['put', 'patch'], '/admin/layanan/{layanan}', [LayananController::class, 'update'])
        ->middleware('role:Manajer');
    Route::delete('/admin/layanan/{layanan}', [LayananController::class, 'destroy'])
        ->middleware('role:Manajer');

    Route::get('/admin/produk', [ProdukController::class, 'index'])
        ->middleware('role:Manajer');
    Route::post('/admin/produk', [ProdukController::class, 'store'])
        ->middleware('role:Manajer');
    Route::get('/admin/produk/{produk}', [ProdukController::class, 'show'])
        ->middleware('role:Manajer');
    Route::match(['put', 'patch'], '/admin/produk/{produk}', [ProdukController::class, 'update'])
        ->middleware('role:Manajer');
    Route::delete('/admin/produk/{produk}', [ProdukController::class, 'destroy'])
        ->middleware('role:Manajer');

    Route::get('/admin/users', [UserAdminController::class, 'index'])
        ->middleware('role:Manajer');
    Route::post('/admin/users', [UserAdminController::class, 'store'])
        ->middleware('role:Manajer');
    Route::get('/admin/users/{user}', [UserAdminController::class, 'show'])
        ->middleware('role:Manajer');
    Route::match(['put', 'patch'], '/admin/users/{user}', [UserAdminController::class, 'update'])
        ->middleware('role:Manajer');
    Route::delete('/admin/users/{user}', [UserAdminController::class, 'destroy'])
        ->middleware('role:Manajer');

    // Per revisi R3 — supplier master CRUD.
    Route::get('/admin/suppliers', [SupplierController::class, 'index'])
        ->middleware('role:Manajer');
    Route::post('/admin/suppliers', [SupplierController::class, 'store'])
        ->middleware('role:Manajer');
    Route::match(['put', 'patch'], '/admin/suppliers/{supplier}', [SupplierController::class, 'update'])
        ->middleware('role:Manajer');
    Route::delete('/admin/suppliers/{supplier}', [SupplierController::class, 'destroy'])
        ->middleware('role:Manajer');
});

// Lightweight read-only list for the Gudang / POS drawer — any authenticated
// role may populate the dropdown (they can also see what suppliers exist),
// but only Gudang/Manajer can write purchase orders.
Route::middleware('auth:sanctum')->get('/suppliers', [SupplierController::class, 'index']);

// Photo proxy — browser-safe signed URL for <img>; auth stays on the data APIs.
Route::get('/photos/{photo}/raw', [RekamMedisController::class, 'streamPhoto'])
    ->middleware('signed')
    ->name('photos.raw');

// Telegram webhook — public (Telegram servers call this, no auth)
// Webhook secret verification would be added via X-Telegram-Bot-Api-Secret-Token header in production.
Route::match(['get', 'post'], '/telegram/webhook', [TelegramController::class, 'webhook']);

Route::get('/health', fn () => response()->json(['ok' => true]));
