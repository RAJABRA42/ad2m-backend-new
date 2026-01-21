<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'administrateur',
                'display_name' => 'Administrateur Système (AD2M)',
                'description' => 'Accès complet au système, gestion des utilisateurs et des rôles.'
            ],
            [
                'name' => 'raf',
                'display_name' => 'Responsable Administratif et Financier',
                'description' => 'Validation budgétaire / audit.'
            ],
            [
                'name' => 'accp',
                'display_name' => 'Agent Comptable et Contrôle des Paiements (ACCP)',
                'description' => 'Paiements, avances, clôture financière.'
            ],
            [
                'name' => 'chef_hierarchique',
                'display_name' => 'Chef Hiérarchique (CH)',
                'description' => 'Validation hiérarchique.'
            ],
            [
                'name' => 'coordonnateur_de_projet',
                'display_name' => 'Coordonnateur Projet (CP)',
                'description' => 'Validation CP.'
            ],
            [
                'name' => 'missionnaire',
                'display_name' => 'Missionnaire',
                'description' => 'Création des missions.'
            ],
            [
                'name' => 'assistant_administratif',
                'display_name' => 'Assistant Administratif (AADM)',
                'description' => 'Support administratif.'
            ],
        ];

        foreach ($roles as $r) {
            Role::updateOrCreate(
                ['name' => $r['name']],
                [
                    'display_name' => $r['display_name'],
                    'description' => $r['description'],
                ]
            );
        }
    }
}
