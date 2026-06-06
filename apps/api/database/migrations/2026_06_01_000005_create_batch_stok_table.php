<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_stok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->string('kode_batch', 30);
            $table->integer('qty');
            $table->integer('hpp');
            $table->date('kadaluarsa')->nullable();
            $table->string('supplier', 60);
            $table->timestamps();

            $table->index(['produk_id', 'kadaluarsa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_stok');
    }
};
