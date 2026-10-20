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
            $table->unsignedBigInteger('sanction')->default(0)->after('cooperative_deduction');
            $table->unsignedBigInteger('honor_received')->default(0)->after('sanction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_slips', function (Blueprint $table) {
            $table->dropColumn(['sanction', 'honor_received']);
        });
    }
};
