<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate(['username' => 'admin'], [
            'name' => 'Administrator EKLESIA',
            'email' => 'admin@eklesia.test',
            'password' => 'admin03082003',
            'role' => 'admin',
            'work_days' => null,
            'work_start' => null,
            'work_end' => null,
        ]);
    }
}
