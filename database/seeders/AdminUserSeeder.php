<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Adresse;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Exécute le seeder.
     */
    public function run(): void
    {
        $adminEmail = 'issabarry67@gmail.com';

        // 🔹 1. Vérifier ou créer le rôle Administrateur
        $role = Role::firstOrCreate(
            ['name' => 'Administrateur'],
            ['guard_name' => 'web'] // Spatie nécessite le guard_name
        );

        // 🔹 2. Vérifier si l’utilisateur admin existe déjà
        $admin = User::where('email', $adminEmail)->first();

        if (!$admin) {
            // 🏠 Créer l’adresse associée
            $adresse = Adresse::create([
                'pays'               => 'France',
                'adresse'            => '123 rue de l’Administration',
                'complement_adresse' => 'Appartement 45',
                'ville'              => 'Paris',
                'code_postal'        => '75000',
                'region'             => 'Île-de-France',
            ]);

            // 👤 Créer l’utilisateur admin
            $admin = User::create([
                'civilite'       => 'Mr',
                'prenom'         => 'Issa',
                'nom'            => 'Barry',
                'email'          => $adminEmail,
                'phone'          => '0123456789',
                'date_naissance' => '1985-01-01',
                'password'       => Hash::make('Jeux@2019'), // ⚠️ À changer en prod
                'adresse_id'     => $adresse->id,
                'role_id'        => $role->id,
                'statut'         => 'active',
            ]);

            // 🔗 Assigner le rôle Spatie
            $admin->assignRole($role->name);

            // (Optionnel) Envoyer un email de vérification
            // $admin->sendEmailVerificationNotification();

            $this->command->info('✅ Administrateur créé avec succès : '.$adminEmail);
        } else {
            $this->command->warn('⚠️ L’utilisateur admin existe déjà : '.$adminEmail);
        }
    }

    // php artisan db:seed --class=AdminUserSeeder
}
