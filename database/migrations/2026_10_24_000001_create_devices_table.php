<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devices allowed to use the kiosk and Clock In/Out (T-11.1). A device is a browser on a phone,
 * tablet or computer, remembered by a cookie whose token is stored only as a SHA-256 hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('code', 7)->unique();
            $table->string('name', 100)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('requested_from', 10);
            $table->foreignId('requested_by')->nullable()->constrained('users');
            $table->dateTime('requested_at');
            $table->dateTime('last_seen_at');
            $table->string('browser', 100);
            $table->string('platform', 100);
            $table->text('user_agent');
            $table->string('ip_address', 45);
            $table->foreignId('last_user_id')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
