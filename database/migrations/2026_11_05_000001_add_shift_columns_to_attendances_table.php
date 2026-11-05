<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-11.3: the shift windows saved at clock-in (null = Flexible) and the real clocked minutes.
 * minutes_worked becomes the minutes inside the shift, which hours and labor cost are based on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->json('shift_windows')->nullable()->after('hourly_rate');
            $table->unsignedInteger('clocked_minutes')->nullable()->after('minutes_worked');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['shift_windows', 'clocked_minutes']);
        });
    }
};
