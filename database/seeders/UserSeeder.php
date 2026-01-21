<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    private function upsertUser(array $data, array $roleNames = []): User
    {
        $user = User::updateOrCreate(
            ['email' => $data['email']],
            [
                'matricule' => $data['matricule'],
                'nom' => $data['nom'],
                'name' => $data['name'],
                'telephone' => $data['telephone'] ?? null,
                'unite' => $data['unite'] ?? null,
                'poste' => $data['poste'] ?? null,
                'chef_hierarchique_id' => $data['chef_hierarchique_id'] ?? null,
                'status' => $data['status'] ?? 'active',
                'password' => Hash::make($data['password'] ?? 'password'),
            ]
        );

        if (!empty($roleNames)) {
            $roleIds = Role::whereIn('name', $roleNames)->pluck('id')->toArray();
            // ✅ évite doublons en pivot
            $user->roles()->sync($roleIds);
        }

        return $user;
    }

    public function run(): void
    {
        // --- utilisateurs "noyau"
        $admin = $this->upsertUser([
            'matricule' => 'AD2M001',
            'nom' => 'Administrateur',
            'name' => 'Admin AD2M',
            'email' => 'admin@ad2m.com',
            'telephone' => '123456789',
            'unite' => 'Direction',
            'poste' => 'Administrateur Système',
            'password' => 'password',
            'chef_hierarchique_id' => null,
        ], ['administrateur']);

        $ch = $this->upsertUser([
            'matricule' => 'CH001',
            'nom' => 'Chef',
            'name' => 'Chef Hiérarchique',
            'email' => 'ch@example.com',
            'telephone' => '22222222',
            'unite' => 'Opérations',
            'poste' => "Chef d'Unité",
            'password' => 'password',
            'chef_hierarchique_id' => $admin->id,
        ], ['chef_hierarchique']);

        $raf = $this->upsertUser([
            'matricule' => 'R001',
            'nom' => 'Responsable',
            'name' => 'RAF Finance',
            'email' => 'raf@example.com',
            'telephone' => '33333333',
            'unite' => 'Finance',
            'poste' => 'Responsable Administratif',
            'password' => 'password',
            'chef_hierarchique_id' => $admin->id,
        ], ['raf']);

        $cp = $this->upsertUser([
            'matricule' => 'CP001',
            'nom' => 'Coordonnateur',
            'name' => 'Chef de Projet Alpha',
            'email' => 'cp@example.com',
            'telephone' => '44444444',
            'unite' => 'Projet A',
            'poste' => 'Coordonnateur de Projet',
            'password' => 'password',
            'chef_hierarchique_id' => $admin->id,
        ], ['coordonnateur_de_projet']);

        $accp = $this->upsertUser([
            'matricule' => 'AC001',
            'nom' => 'Agent',
            'name' => 'ACCP Avances/Paiements',
            'email' => 'accp@example.com',
            'telephone' => '66666666',
            'unite' => 'Comptabilité',
            'poste' => 'Agent Comptable',
            'password' => 'password',
            'chef_hierarchique_id' => $raf->id,
        ], ['accp']);

        // --- missionnaire "principal" (tu l'utilises déjà)
        $this->upsertUser([
            'matricule' => 'M001',
            'nom' => 'Missionnaire',
            'name' => 'Demandeur de Mission Alpha',
            'email' => 'user@example.com',
            'telephone' => '55555555',
            'unite' => 'Opérations',
            'poste' => 'Technicien',
            'password' => 'password',
            'chef_hierarchique_id' => $ch->id,
        ], ['missionnaire']);

        // ✅ Ajouter 20 missionnaires simples (M002..M021)
        for ($i = 2; $i <= 21; $i++) {
            $num = str_pad((string)$i, 3, '0', STR_PAD_LEFT);
            $this->upsertUser([
                'matricule' => "M{$num}",
                'nom' => "Missionnaire {$num}",
                'name' => "User Mission {$num}",
                'email' => "mission{$num}@example.com",
                'telephone' => "03400{$num}{$num}",
                'unite' => ($i % 2 === 0) ? 'Opérations' : 'Projet A',
                'poste' => 'Agent terrain',
                'password' => 'password',
                'chef_hierarchique_id' => $ch->id,
            ], ['missionnaire']);
        }

        // ✅ Ajouter 3 assistants admin (A001..A003)
        for ($i = 1; $i <= 3; $i++) {
            $num = str_pad((string)$i, 3, '0', STR_PAD_LEFT);
            $this->upsertUser([
                'matricule' => "A{$num}",
                'nom' => "Assistant {$num}",
                'name' => "Assistant Admin {$num}",
                'email' => "assistant{$num}@example.com",
                'telephone' => "03299{$num}{$num}",
                'unite' => 'Administration',
                'poste' => 'Assistant',
                'password' => 'password',
                'chef_hierarchique_id' => $admin->id,
            ], ['assistant_administratif']);
        }
    }
}
