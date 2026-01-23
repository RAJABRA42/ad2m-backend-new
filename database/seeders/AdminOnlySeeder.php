<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminOnlySeeder extends Seeder
{
    public function run(): void
    {
        // Nettoyage total
        User::query()->delete();
        Role::query()->delete();

        // Création rôle admin
        $adminRole = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        // Création user admin
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@ad2m.test',
            'password' => Hash::make('password'),
        ]);

        $admin->assignRole($adminRole);
    }
}
