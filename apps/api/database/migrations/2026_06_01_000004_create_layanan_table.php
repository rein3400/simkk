<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layanan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 80);
            $table->string('kategori', 20);
            $table->string('durasi', 20)->nullable();
            $table->integer('harga');
            $table->decimal('komisi_rate', 4, 2);
            $table->foreignId('stok_produk_id')->nullable()->constrained('produk');
            $table->string('dampak_stok', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layanan');
    }
};
