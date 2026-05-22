<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->upsert([
            ['id' => 'u-denis', 'name' => 'Denis', 'role' => 'Senior Engineer'],
            ['id' => 'u-anna', 'name' => 'Anna', 'role' => 'Field Coordinator'],
            ['id' => 'u-mark', 'name' => 'Mark', 'role' => 'Support Engineer'],
            ['id' => 'u-kate', 'name' => 'Kate', 'role' => 'Project Manager'],
            ['id' => 'u-ivan', 'name' => 'Ivan', 'role' => 'Remote Office Lead'],
        ], ['id'], ['name', 'role']);
    }
}
