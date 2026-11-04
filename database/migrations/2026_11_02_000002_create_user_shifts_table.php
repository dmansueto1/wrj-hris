<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-11.2: a user's weekly shift, one row per weekday (ISO: 1 = Monday … 7 = Sunday).
 * One shift a day uses first_in/first_out; two shifts add second_in/second_out (afternoon).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedTinyInteger('weekday');
            $table->boolean('is_rest_day')->default(false);
            $table->time('first_in')->nullable();
            $table->time('first_out')->nullable();
            $table->time('second_in')->nullable();
            $table->time('second_out')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_shifts');
    }
};
