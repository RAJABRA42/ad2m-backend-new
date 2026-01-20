<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Exécute les seeds pour la base de données.
     * Crée tous les utilisateurs de test et leur attribue un ou plusieurs rôles via la table pivot.
     */
    public function run(): void
    {
        // Noms des rôles à rechercher pour l'attribution
        $roleNames = [
            'administrateur',
            'chef_hierarchique',
            'raf',
            'coordonnateur_de_projet',
            'accp',
            'missionnaire',
        ];

        // 1. Trouver les IDs des rôles
        try {
            // Récupère les IDs des rôles et les stocke dans un tableau associatif [ 'name' => id ]
            $roleIds = Role::whereIn('name', $roleNames)->pluck('id', 'name')->toArray();

            // Vérifier si tous les rôles critiques sont présents
            if (count($roleIds) !== count($roleNames)) {
                 Log::error("Erreur critique : Certains rôles de validation sont manquants dans la table 'roles'.");
                 return;
            }

        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des rôles : " . $e->getMessage());
            return;
        }

        // --- 2. Création des Utilisateurs et Hiérarchie (Utilisation de roles()->attach()) ---

        // A. Créer l'ADMINISTRATEUR (Niveau 0 - Top de la hiérarchie)
        $adminUser = User::create([
            'matricule' => 'AD2M001',
            'nom' => 'Administrateur',
            'name' => 'Admin AD2M',
            'email' => 'admin@ad2m.com',
            'telephone' => '123456789',
            'unite' => 'Direction',
            'poste' => 'Administrateur Système',
            'status' => 'active',
            'password' => Hash::make('password'),
            'chef_hierarchique_id' => null,
            // 'role_id' n'est plus utilisé ici
        ]);
        // ATTRIBUTION VIA LA TABLE PIVOT
        $adminUser->roles()->attach($roleIds['administrateur']);
        Log::info("Utilisateur Administrateur créé.");


        // B. Créer les Valideurs N-1 (Rapportent à l'Admin)

        // B1. CHEF HIÉRARCHIQUE (CH)
        $chefHierarchique = User::create([
            'matricule' => 'CH001',
            'nom' => 'Chef',
            'name' => 'Chef Hiérarchique',
            'email' => 'ch@example.com',
            'telephone' => '22222222',
            'unite' => 'Opérations',
            'poste' => 'Chef d\'Unité',
            'status' => 'active',
            'password' => Hash::make('password'),
            'chef_hierarchique_id' => $adminUser->id,
        ]);
        // ATTRIBUTION VIA LA TABLE PIVOT
        $chefHierarchique->roles()->attach($roleIds['chef_hierarchique']);
        Log::info("Utilisateur Chef Hiérarchique créé.");


        // B2. RAF (Responsable Administratif et Financier)
        $raf = User::create([
            'matricule' => 'R001',
            'nom' => 'Responsable',
            'name' => 'RAF Finance',
            'email' => 'raf@example.com',
            'telephone' => '33333333',
            'unite' => 'Finance',
            'poste' => 'Responsable Administratif',
            'status' => 'active',
            'password' => Hash::make('password'),
            'chef_hierarchique_id' => $adminUser->id,
        ]);
        // ATTRIBUTION VIA LA TABLE PIVOT
        $raf->roles()->attach($roleIds['raf']);
        Log::info("Utilisateur RAF créé.");


        // B3. CP (Chef de Projet)
        $cp = User::create([
            'matricule' => 'CP001',
            'nom' => 'Coordonnateur',
            'name' => 'Chef de Projet Alpha',
            'email' => 'cp@example.com',
            'telephone' => '44444444',
            'unite' => 'Projet A',
            'poste' => 'Coordonnateur de Projet',
            'status' => 'active',
            'password' => Hash::make('password'),
            'chef_hierarchique_id' => $adminUser->id,
        ]);
        // ATTRIBUTION VIA LA TABLE PIVOT
        $cp->roles()->attach($roleIds['coordonnateur_de_projet']);
        Log::info("Utilisateur Chef de Projet créé.");


        // C. Créer l'ACCP (Rapporte au RAF)
        $accp = User::create([
            'matricule' => 'AC001',
            'nom' => 'Agent',
            'name' => 'ACCP Avances/Paiements',
            'email' => 'accp@example.com',
            'telephone' => '66666666',
            'unite' => 'Comptabilité',
            'poste' => 'Agent Comptable',
            'status' => 'active',
            'password' => Hash::make('password'),
            'chef_hierarchique_id' => $raf->id,
        ]);
        // ATTRIBUTION VIA LA TABLE PIVOT
        $accp->roles()->attach($roleIds['accp']);
        Log::info("Utilisateur ACCP créé.");


        // D. Créer le Demandeur/Missionnaire (Rapporte au CH)
        $missionnaire = User::create([
            'matricule' => 'M001',
            'nom' => 'Missionnaire',
            'name' => 'Demandeur de Mission Alpha',
            'email' => 'user@example.com',
            'telephone' => '55555555',
            'unite' => 'Opérations',
            'poste' => 'Technicien',
            'status' => 'active',
            'password' => Hash::make('password'),
            'chef_hierarchique_id' => $chefHierarchique->id,
        ]);
        // ATTRIBUTION VIA LA TABLE PIVOT
        $missionnaire->roles()->attach($roleIds['missionnaire']);
        Log::info("Utilisateur Missionnaire (user@example.com) créé.");
    }
}
