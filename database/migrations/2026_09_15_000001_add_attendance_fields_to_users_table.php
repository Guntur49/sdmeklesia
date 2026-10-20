<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('role')->default('staff')->after('password');
            $table->json('work_days')->nullable()->after('role');
            $table->time('work_start')->nullable()->after('work_days');
            $table->time('work_end')->nullable()->after('work_start');
            $table->string('face_reference')->nullable()->after('work_end');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'role', 'work_days', 'work_start', 'work_end', 'face_reference']);
        });
    }
};
