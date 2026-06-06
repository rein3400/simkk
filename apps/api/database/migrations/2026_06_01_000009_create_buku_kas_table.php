<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buku_kas', function (Blueprint $table) {
            $table->id();
            $table->string('id_transaksi', 30);
            $table->string('tipe', 10);
            $table->integer('jumlah');
            $table->string('deskripsi', 255);
            $table->timestamps();

            $table->foreign('id_transaksi')->references('id_transaksi')->on('transaksi')->cascadeOnDelete();
            $table->index('id_transaksi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buku_kas');
    }
};
