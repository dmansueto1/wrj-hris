<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_no', 30)->unique()->after('id');
            $table->string('first_name', 100)->after('employee_no');
            $table->string('last_name', 100)->after('first_name');
            $table->string('username', 50)->unique()->after('name');
            $table->string('email')->nullable()->change();
            $table->string('position')->nullable()->after('email');
            $table->decimal('hourly_rate', 10, 2)->default(0)->after('position');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('hourly_rate');
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // email becomes required again, so give users without one a unique placeholder.
        DB::table('users')->whereNull('email')->update([
            'email' => DB::raw("CONCAT(username, '@no-email.invalid')"),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['employee_no']);
            $table->dropUnique(['username']);
            $table->dropColumn([
                'employee_no',
                'first_name',
                'last_name',
                'username',
                'position',
                'hourly_rate',
                'status',
                'must_change_password',
                'deleted_at',
            ]);
            $table->string('email')->nullable(false)->change();
        });
    }
};
