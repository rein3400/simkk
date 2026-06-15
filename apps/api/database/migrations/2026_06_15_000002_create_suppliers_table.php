<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per revisi R3 — "tidak bisa manual" — supplier master table.
     * Replaces free-text `supplier` string on pembelian_supplier with a
     * FK to a registered supplier. Legacy rows keep the string until
     * manual backfill.
     */
    public function up(): void
    {
        Schema::create('supplier', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 80)->unique();
            $table->string('kontak', 80)->nullable();
            $table->string('telepon', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('alamat')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('pembelian_supplier', function (Blueprint $table) {
            if (!Schema::hasColumn('pembelian_supplier', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('kode_batch');
                $table->foreign('supplier_id')->references('id')->on('supplier')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pembelian_supplier', function (Blueprint $table) {
            if (Schema::hasColumn('pembelian_supplier', 'supplier_id')) {
                $table->dropForeign(['supplier_id']);
                $table->dropColumn('supplier_id');
            }
        });
        Schema::dropIfExists('supplier');
    }
};
