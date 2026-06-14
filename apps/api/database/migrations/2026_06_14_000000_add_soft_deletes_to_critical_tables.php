<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // P1 #5: Soft deletes on all critical tables.
        // Preserves audit trail when operator accidentally deletes a row.
        // Legal requirement for medical records (minimum retention period).

        Schema::table('pasien', function (Blueprint $table) {
            if (!Schema::hasColumn('pasien', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('transaksi', function (Blueprint $table) {
            if (!Schema::hasColumn('transaksi', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('transaksi_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('transaksi_detail', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('buku_kas', function (Blueprint $table) {
            if (!Schema::hasColumn('buku_kas', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('batch_stok', function (Blueprint $table) {
            if (!Schema::hasColumn('batch_stok', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('pembelian_supplier', function (Blueprint $table) {
            if (!Schema::hasColumn('pembelian_supplier', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('layanan', function (Blueprint $table) {
            if (!Schema::hasColumn('layanan', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('produk', function (Blueprint $table) {
            if (!Schema::hasColumn('produk', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pasien', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('transaksi', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('transaksi_detail', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('buku_kas', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('batch_stok', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('pembelian_supplier', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('layanan', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('produk', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
