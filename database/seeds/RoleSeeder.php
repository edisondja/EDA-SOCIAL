<?php

use App\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['name' => 'admin', 'label' => 'Administrador'],
            ['name' => 'moderator', 'label' => 'Moderador'],
            ['name' => 'user', 'label' => 'Usuario'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
