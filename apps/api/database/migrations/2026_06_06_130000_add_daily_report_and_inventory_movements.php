<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_cash_float', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('tanggal');
            $table->integer('modal_awal');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tanggal'], 'uniq_daily_cash_float_user_date');
        });

        Schema::create('daily_closing', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique();
            $table->foreignId('user_kasir_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('user_manajer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('status', 15)->default('draft');
            $table->integer('total_penjualan')->default(0);
            $table->integer('total_card')->default(0);
            $table->integer('total_tunai')->default(0);
            $table->integer('pnl')->default(0);
            $table->integer('setoran_bank')->default(0);
            $table->string('signature_kasir_path')->nullable();
            $table->string('signature_manajer_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('user_kasir_id');
        });

        Schema::create('stok_mutasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produk')->constrained('produk')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('tipe', 30);
            $table->string('arah', 3);
            $table->decimal('qty', 10, 2);
            $table->foreignId('id_batch')->nullable()->constrained('batch_stok')->nullOnDelete();
            $table->foreignId('id_transaksi')->nullable()->constrained('transaksi')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['id_produk', 'tanggal'], 'idx_stok_mutasi_produk_tanggal');
            $table->index(['tipe', 'arah'], 'idx_stok_mutasi_tipe_arah');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('shift');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });

        Schema::dropIfExists('stok_mutasi');
        Schema::dropIfExists('daily_closing');
        Schema::dropIfExists('daily_cash_float');
    }
};
