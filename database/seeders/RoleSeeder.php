<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         // 1. Administrateur (AD2M)
        Role::create([
            'name' => 'administrateur',
            'display_name' => 'Administrateur Système (AD2M)',
            'description' => 'Accès complet au système, gestion des utilisateurs et des rôles.'
        ]);

        // 2. Responsable Administratif et Financier (RAF)
        Role::create([
            'name' => 'raf',
            'display_name' => 'Responsable Administratif et Financier',
            'description' => 'Audit, pilotage et gestion du risque global (Validation Budgétaire).'
        ]);

        // 3. Agent Comptable et Contrôle des Paiements (ACCP)
        Role::create([
            'name' => 'accp',
            'display_name' => 'Agent Comptable et Contrôle des Paiements (ACCP)',
            'description' => 'Enregistrement des paiements, des avances et clôture financière.'
        ]);

        // 4. Chef Hiérarchique (CH) - Validation de la hiérarchie
        Role::create([
            'name' => 'chef_hierarchique',
            'display_name' => 'Chef Hiérarchique (CH)',
            'description' => 'Validation et autorisation hiérarchique des missions.'
        ]);

        // 5. Chef de Projet (CP) - Validation du budget projet
        Role::create([
            'name' => 'coordonnateur_de_projet',
            'display_name' => 'Coordonnateur Projet (CP)',
            'description' => 'Supervision et validation des missions de son projet.'
        ]);

        // 6. Missionnaire (M) - Créateur de la demande
        Role::create([
            'name' => 'missionnaire',
            'display_name' => 'Missionnaire',
            'description' => 'Création des missions, saisie des données et upload des preuves.'
        ]);

        // 7. Assistant Administratif (AADM) - Support
        Role::create([
            'name' => 'assistant_administratif',
            'display_name' => 'Assistant Administratif (AADM)',
            'description' => 'Saisie et préparation des ordres de mission (OM) et suivi administratif de base.'
        ]);
    }
}
