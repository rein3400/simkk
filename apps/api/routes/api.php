<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\InventarisController;
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

    Route::post('/telegram/reminder', [TelegramController::class, 'reminder'])
        ->middleware('role:Manajer,Kasir,Terapis');
    Route::post('/telegram/aftercare', [TelegramController::class, 'aftercare'])
        ->middleware('role:Manajer,Terapis');
});

Route::get('/health', fn () => response()->json(['ok' => true]));
