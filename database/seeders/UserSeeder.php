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
            $user->roles()->sync($roleIds);
        }

        return $user;
    }

    public function run(): void
    {
        // Noyau
        $admin = $this->upsertUser([
            'matricule' => 'AD2M001',
            'nom' => 'Administrateur',
            'name' => 'Admin AD2M',
            'email' => 'admin@ad2m.com',
            'telephone' => '0340000001',
            'unite' => 'Direction',
            'poste' => 'Administrateur',
            'password' => 'password',
        ], ['administrateur']);

        $ch = $this->upsertUser([
            'matricule' => 'CH001',
            'nom' => 'Chef',
            'name' => 'Chef Hiérarchique',
            'email' => 'ch@example.com',
            'telephone' => '0340000002',
            'unite' => 'Opérations',
            'poste' => "Chef d'Unité",
            'password' => 'password',
            'chef_hierarchique_id' => $admin->id,
        ], ['chef_hierarchique']);

        $raf = $this->upsertUser([
            'matricule' => 'RAF001',
            'nom' => 'Responsable',
            'name' => 'RAF Finance',
            'email' => 'raf@example.com',
            'telephone' => '0340000003',
            'unite' => 'Finance',
            'poste' => 'RAF',
            'password' => 'password',
            'chef_hierarchique_id' => $admin->id,
        ], ['raf']);

        $cp = $this->upsertUser([
            'matricule' => 'CP001',
            'nom' => 'Coordonnateur',
            'name' => 'Coordonnateur Projet',
            'email' => 'cp@example.com',
            'telephone' => '0340000004',
            'unite' => 'Projet',
            'poste' => 'CP',
            'password' => 'password',
            'chef_hierarchique_id' => $admin->id,
        ], ['coordonnateur_de_projet']);

        $accp = $this->upsertUser([
            'matricule' => 'ACCP001',
            'nom' => 'Agent',
            'name' => 'ACCP Paiements',
            'email' => 'accp@example.com',
            'telephone' => '0340000005',
            'unite' => 'Comptabilité',
            'poste' => 'ACCP',
            'password' => 'password',
            'chef_hierarchique_id' => $raf->id,
        ], ['accp']);

        // 5 missionnaires (M001..M005)
        for ($i = 1; $i <= 5; $i++) {
            $num = str_pad((string)$i, 3, '0', STR_PAD_LEFT);
            $this->upsertUser([
                'matricule' => "M{$num}",
                'nom' => "Missionnaire {$num}",
                'name' => "User Mission {$num}",
                'email' => "mission{$num}@example.com",
                'telephone' => "0341000{$num}",
                'unite' => 'Opérations',
                'poste' => 'Agent terrain',
                'password' => 'password',
                'chef_hierarchique_id' => $ch->id,
            ], ['missionnaire']);
        }
    }
}
