<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // F-007 fix: Terapis can only write treatments for patients assigned to them.
        // Nullable for backward compat; Manajer bypasses the check (still allowed by route middleware).
        Schema::table('pasien', function (Blueprint $table) {
            $table->foreignId('assigned_terapis_id')->nullable()->after('risk_note')->constrained('terapis')->nullOnDelete();
            $table->index('assigned_terapis_id');
        });
    }

    public function down(): void
    {
        Schema::table('pasien', function (Blueprint $table) {
            $table->dropForeign(['assigned_terapis_id']);
            $table->dropIndex(['assigned_terapis_id']);
            $table->dropColumn('assigned_terapis_id');
        });
    }
};
