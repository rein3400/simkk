<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_detail', function (Blueprint $table) {
            $table->id();
            $table->string('id_transaksi', 30);
            $table->foreignId('id_produk')->constrained('layanan');
            $table->foreignId('id_terapis')->nullable()->constrained('terapis');
            $table->integer('nilai_komisi');
            $table->integer('qty')->default(1);
            $table->integer('harga_satuan');
            $table->timestamps();

            $table->foreign('id_transaksi')->references('id_transaksi')->on('transaksi')->cascadeOnDelete();
            $table->index('id_transaksi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_detail');
    }
};
