<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_rate_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->decimal('hourly_rate', 10, 2);
            $table->date('effective_from');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_rate_histories');
    }
};
