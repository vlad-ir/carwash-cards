<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RolesTableSeeder extends Seeder
{
    public function run(): void
    {
        Role::create([
            ['id' => 1, 'name' => 'admin', 'description' => 'Администратор системы'],
            ['id' => 2, 'name' => 'client', 'description' => 'Клиент автомойки'],
            ['id' => 3, 'name' => 'accountant', 'description' => 'Бухгалтер'],
        ]);
    }
}
