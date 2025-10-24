<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'administrateur',       'trigramme' => 'adm'],
            ['name' => 'client',               'trigramme' => 'cli'],
            ['name' => 'manager',                'trigramme' => 'mng'],
            ['name' => 'comptable',   'trigramme' => 'com'],
            ['name' => 'vendeur',   'trigramme' => 'ven'],
            ['name' => 'consultant',   'trigramme' => 'csl'],
            ['name' => 'packing',    'trigramme' => 'pac'],
            ['name' => 'livreur',    'trigramme' => 'liv'],
            ['name' => 'vehicule',    'trigramme' => 'veh'],

        ];

        foreach ($roles as $data) {
            $name = strtolower($data['name']);        // force en minuscule
            $trigramme = strtolower($data['trigramme']); // force en minuscule aussi

            // Vérifie si un autre rôle utilise déjà ce trigramme
            $exists = Role::where('trigramme', $trigramme)
                ->where('name', '!=', $name)
                ->exists();

            if ($exists) {
                $this->command->error(" Le trigramme '{$trigramme}' est déjà utilisé !");
                continue;
            }

            Role::updateOrCreate(
                ['name' => $name],
                [
                    'guard_name' => 'web',
                    'trigramme' => $trigramme,
                ]
            );
        }

        $this->command->info(' Les rôles ont été créés avec succès en minuscules !');
    }
    //à lancé en 1er : php artisan db:seed --class=RoleSeeder
}
