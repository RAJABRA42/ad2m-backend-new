<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'administrateur',         'display_name' => 'Administrateur',      'description' => 'Accès complet.'],
            // (optionnel) alias si tu as des checks "admin"
            ['name' => 'admin',                  'display_name' => 'Admin',               'description' => 'Alias administrateur.'],

            ['name' => 'chef_hierarchique',      'display_name' => 'Chef Hiérarchique',   'description' => 'Validation hiérarchique.'],
            ['name' => 'raf',                    'display_name' => 'RAF',                 'description' => 'Validation budgétaire.'],
            ['name' => 'coordonnateur_de_projet','display_name' => 'CP',                  'description' => 'Validation CP.'],
            ['name' => 'accp',                   'display_name' => 'ACCP',                'description' => 'Paiement / avances.'],
            ['name' => 'missionnaire',           'display_name' => 'Missionnaire',        'description' => 'Création des missions.'],
            ['name' => 'assistant_administratif','display_name' => 'Assistant admin',     'description' => 'Support administratif.'],
        ];

        foreach ($roles as $r) {
            Role::updateOrCreate(
                ['name' => $r['name']],
                ['display_name' => $r['display_name'], 'description' => $r['description']]
            );
        }
    }
}
