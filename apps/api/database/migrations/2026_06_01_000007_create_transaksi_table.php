<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi', function (Blueprint $table) {
            // F-004 fix: surrogate auto-increment id is the PK; id_transaksi is a
            // derived business identifier, unique but not the PK, generated from
            // the surrogate id after insert to avoid the count()+1 race.
            $table->bigIncrements('id');
            $table->string('id_transaksi', 30)->unique();
            $table->foreignId('pasien_id')->constrained('pasien');
            $table->foreignId('terapis_id')->nullable()->constrained('terapis');
            $table->string('status', 15)->default('Draft');
            $table->integer('subtotal')->default(0);
            $table->integer('diskon')->default(0);
            $table->string('metode_bayar', 32)->default('Tunai');
            $table->integer('total');
            $table->integer('komisi_total');
            $table->string('waktu', 10);
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
