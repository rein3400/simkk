<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terapis', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 50);
            $table->string('spesialisasi', 50)->nullable();
            $table->string('status', 15)->default('Tersedia');
            $table->integer('gaji_pokok')->default(2500000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terapis');
    }
};
