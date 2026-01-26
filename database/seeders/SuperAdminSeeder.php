<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Créer les rôles
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'formateur', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'apprenant', 'guard_name' => 'web']);


        // Créer le Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@elearning.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Admin@2025'),
                'role' => 'super_admin',
                'is_active' => true,
                'profile_completed' => true,
                'email_verified_at' => now(),
            ]
        );

        $superAdmin->assignRole('super_admin');

        $this->command->info('✅ Super Admin créé avec succès!');
        $this->command->info('📧 Email: admin@elearning.com');
        $this->command->info('🔑 Password: Admin@2025');
    }
}