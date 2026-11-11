<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `was_flagged`: the record was created by correcting a flagged attendance (T-13), directly or
     * through earlier corrections, so the remarks can say "Flagged, corrected".
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('was_flagged')->default(false)->after('corrected_from_id');
        });

        // Existing corrections: a record before its corrections always has the lower id.
        $marked = [];

        DB::table('attendances')
            ->whereNotNull('corrected_from_id')
            ->orderBy('id')
            ->get(['id', 'corrected_from_id'])
            ->each(function (object $row) use (&$marked) {
                $parent = DB::table('attendances')->where('id', $row->corrected_from_id)->first(['id', 'status']);

                if ($parent !== null && ($parent->status === 'flagged' || isset($marked[$parent->id]))) {
                    $marked[$row->id] = true;
                }
            });

        foreach (array_chunk(array_keys($marked), 500) as $ids) {
            DB::table('attendances')->whereIn('id', $ids)->update(['was_flagged' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('was_flagged');
        });
    }
};
