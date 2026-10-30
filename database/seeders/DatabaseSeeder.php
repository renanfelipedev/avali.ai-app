<?php

namespace Database\Seeders;

use App\Models\Role;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(PlanSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@email.com'],
            [
                'name' => 'Administrador',
                'password' => 'admin@123',
                'is_active' => true,
            ]
        );

        if (! $admin->roles()->where('slug', 'admin')->exists()) {
            $adminRole = Role::where('slug', 'admin')->first();
            if ($adminRole) {
                $admin->roles()->attach($adminRole->id);
            }
        }
    }
}
