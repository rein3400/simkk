<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foto_klinis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pasien_id')->constrained('pasien')->cascadeOnDelete();
            $table->string('label', 10);
            $table->string('tanggal', 20);
            $table->string('object_ref', 255);
            $table->timestamps();

            $table->index('pasien_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foto_klinis');
    }
};
