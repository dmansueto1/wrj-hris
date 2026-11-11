<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-13: the hourly attendance:flag-open (status open, clock_in_at) and the "Needs correction (N)"
 * count (status flagged) search by status across all users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['status', 'clock_in_at']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['status', 'clock_in_at']);
        });
    }
};
