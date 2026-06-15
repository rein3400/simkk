<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per revisi R1/R2 — booking system with time-slot anti-overlap.
     * The `(terapis_id, scheduled_at)` composite index supports the conflict
     * check query in BookingController::store.
     */
    public function up(): void
    {
        Schema::create('booking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pasien_id')->constrained('pasien')->cascadeOnDelete();
            $table->foreignId('terapis_id')->constrained('terapis')->cascadeOnDelete();
            $table->foreignId('layanan_id')->nullable()->constrained('layanan')->nullOnDelete();
            $table->dateTime('scheduled_at');
            $table->unsignedInteger('duration_min')->default(60);
            $table->enum('status', ['booked', 'confirmed', 'done', 'cancelled', 'no_show'])->default('booked');
            $table->text('notes')->nullable();
            $table->enum('source', ['walk_in', 'phone', 'web'])->default('walk_in');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('scheduled_at');
            $table->index(['terapis_id', 'scheduled_at']);
            $table->index(['pasien_id', 'scheduled_at']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking');
    }
};
