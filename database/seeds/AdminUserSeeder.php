<?php

use App\Channel;
use App\Role;
use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        $role = Role::where('name', 'admin')->first();
        if (!$role) {
            throw new RuntimeException('Falta el rol admin. Ejecuta primero RoleSeeder.');
        }

        $username = 'graned';
        $email = 'graned@eda.social';

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Graned',
                'username' => $username,
                'password' => Hash::make('Meteoro2412'),
                'role_id' => $role->id,
                'status' => 'active',
            ]
        );

        Channel::updateOrCreate(
            ['user_id' => $user->id],
            [
                'slug' => Str::slug($username . '-' . $user->id),
                'display_name' => $user->name,
            ]
        );
    }
}
