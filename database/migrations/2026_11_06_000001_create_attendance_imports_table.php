<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-12.1: one row per uploaded attendance file, with its counts and the rows that were not imported.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_imports', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->foreignId('imported_by')->constrained('users');
            $table->unsignedInteger('rows_total');
            $table->unsignedInteger('rows_imported');
            $table->unsignedInteger('rows_failed');
            $table->json('failures');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_imports');
    }
};
