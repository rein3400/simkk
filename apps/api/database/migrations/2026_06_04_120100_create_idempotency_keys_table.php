<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // F-006 fix: persist Idempotency-Key -> response mapping.
        // Scoped to (user_id, key) so different users with same key don't collide.
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('key_hash', 64);
            $table->string('endpoint', 100);
            $table->unsignedSmallInteger('status_code');
            $table->json('response_body');
            $table->timestamps();

            $table->unique(['user_id', 'key_hash', 'endpoint'], 'idempotency_user_key_endpoint_uq');
            $table->index('created_at'); // for cleanup cron
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
