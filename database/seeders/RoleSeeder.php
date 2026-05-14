<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate(
                ['slug' => $role->value],
                [
                    'name' => $role->label(),
                    'description' => "Perfil de {$role->label()}",
                ]
            );
        }
    }
}
