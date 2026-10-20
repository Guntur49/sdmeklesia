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
        Schema::table('users', function (Blueprint $table) {
            $table->string('employment_status')->default('tetap')->after('role');
            $table->unsignedBigInteger('base_salary')->default(0)->after('employment_status');
            $table->unsignedBigInteger('daily_attendance_allowance')->default(0)->after('base_salary');
            $table->unsignedBigInteger('daily_meal_allowance')->default(0)->after('daily_attendance_allowance');
            $table->unsignedBigInteger('daily_transport_allowance')->default(0)->after('daily_meal_allowance');
            $table->unsignedBigInteger('bpjs_subsidy')->default(0)->after('daily_transport_allowance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'employment_status',
                'base_salary',
                'daily_attendance_allowance',
                'daily_meal_allowance',
                'daily_transport_allowance',
                'bpjs_subsidy',
            ]);
        });
    }
};
