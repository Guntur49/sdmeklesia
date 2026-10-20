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
        Schema::create('payroll_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);
            $table->unsignedBigInteger('base_salary')->default(0);
            $table->unsignedBigInteger('attendance')->default(0);
            $table->unsignedBigInteger('umkm')->default(0);
            $table->unsignedBigInteger('transport')->default(0);
            $table->unsignedBigInteger('bpjs')->default(0);
            $table->unsignedBigInteger('other_honor')->default(0);
            $table->unsignedBigInteger('position_allowance')->default(0);
            $table->unsignedBigInteger('overtime')->default(0);
            $table->unsignedBigInteger('holiday')->default(0);
            $table->unsignedBigInteger('deductions')->default(0);
            $table->unsignedBigInteger('lateness')->default(0);
            $table->unsignedBigInteger('cooperative_deduction')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'month']);
            $table->index('month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_slips');
    }
};
