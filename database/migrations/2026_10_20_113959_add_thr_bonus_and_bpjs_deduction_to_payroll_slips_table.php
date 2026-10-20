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
        Schema::table('payroll_slips', function (Blueprint $table) {
            $table->unsignedBigInteger('thr')->default(0)->after('holiday');
            $table->unsignedBigInteger('bonus')->default(0)->after('thr');
            $table->unsignedBigInteger('bpjs_deduction')->default(0)->after('lateness');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_slips', function (Blueprint $table) {
            $table->dropColumn(['thr', 'bonus', 'bpjs_deduction']);
        });
    }
};
