<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pasien', function (Blueprint $table) {
            $table->id();
            $table->string('nama_pasien', 50);
            $table->integer('usia');
            $table->string('alamat', 100);
            $table->string('nomor_telp', 15);
            $table->string('rekam_medis_id', 20)->unique();
            $table->string('keluhan', 255)->nullable();
            $table->string('last_visit', 30)->nullable();
            $table->string('risk_note', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pasien');
    }
};
