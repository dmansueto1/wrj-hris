<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-12: a correction archives the original attendance (soft delete, values unchanged) and creates
 * the corrected record(s), which point back to it, so the Admin can compare the two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('corrected_from_id')->nullable()->after('adjustment_reason')->constrained('attendances')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corrected_from_id');
            $table->dropSoftDeletes();
        });
    }
};
