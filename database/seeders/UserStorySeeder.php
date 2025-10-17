<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;

class UserStorySeeder extends BaseSeeder
{
    /**
     * Credentials
     */
    const ADMIN_CREDENTIALS = [
        'email' => 'admin@admin.com',
    ];

    public function runFake()
    {
        // Grab all roles for reference
        $roles = Role::all();

        // Create an admin user
        // \App\Models\User::factory()->create([
        //     'name'         => 'Admin',
        //     'email'        => static::ADMIN_CREDENTIALS['email'],
        //     'primary_role' => $roles->where('name', 'admin')->first()->role_id,
        // ]);

        // Create regular user
        // \App\Models\User::factory()->create([
        //     'name'         => 'Bob',
        //     'email'        => 'bob@bob.com',
        //     'primary_role' => $roles->where('name', 'regular')->first()->role_id,
        // ]);

        // Create an admin user with manual UUID
        User::create([
            'user_id'         => 'b13c58ee-ecb0-47c7-a8c4-65b4b4a01a38',
            'name'            => 'Admin',
            'email'           => static::ADMIN_CREDENTIALS['email'],
            'email_verified_at' => now(),
            'password'        => '$2y$12$oxVEYjMOUmFy.JGIfVx/vu4FRP1Yr.KDFhZV46JOtCGx1noKLTGS.',
            'primary_role'    => $roles->where('name', 'admin')->first()->role_id,
            'remember_token'  => '7M0mdSotgo',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Get some random roles to assign to users
        $fakeRolesToAssignCount = 3;
        $fakeRolesToAssign = RoleTableSeeder::getRandomRoles($fakeRolesToAssignCount);

        // Assign fake roles to users
        for ($i = 0; $i < 5; ++$i) {
            $user = \App\Models\User::factory()->create([
                'primary_role' => $roles->random()->role_id,
            ]);

            for ($j = 0; $j < count($fakeRolesToAssign); ++$j) {
                $user->roles()->save($fakeRolesToAssign->shift());
            }
        }
    }
}
