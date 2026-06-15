<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend soft deletes to clinical / personnel tables that were missed
     * in 2026_06_14_000000. Terapis, catatan_treatment, foto_klinis all
     * need it for legal retention and audit-trail safety. Users is
     * intentionally NOT soft-deleted (revoke access via hard delete).
     */
    public function up(): void
    {
        Schema::table('terapis', function (Blueprint $table) {
            if (!Schema::hasColumn('terapis', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('catatan_treatment', function (Blueprint $table) {
            if (!Schema::hasColumn('catatan_treatment', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('foto_klinis', function (Blueprint $table) {
            if (!Schema::hasColumn('foto_klinis', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('terapis', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('catatan_treatment', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('foto_klinis', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
