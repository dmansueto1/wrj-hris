<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Material purchases per project (PRD US-30). Each entry keeps the markup rate it was saved
     * with, so a later change to the setting never alters past costs (PRD 7.4 rule 4).
     */
    public function up(): void
    {
        Schema::create('project_material_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('material_id')->constrained();
            $table->date('entry_date');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('markup_rate', 5, 4);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('markup_amount', 14, 2);
            $table->decimal('total_cost', 14, 2);
            $table->string('remarks')->nullable();
            $table->foreignId('entered_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'entry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_material_entries');
    }
};
