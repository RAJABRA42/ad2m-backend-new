<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    private function upsertUser(array $data, array $roleNames): User
    {
        $user = User::updateOrCreate(
            ['email' => $data['email']],
            [
                'matricule'            => $data['matricule'],
                'nom'                  => $data['nom'],
                'name'                 => $data['name'],
                'telephone'            => $data['telephone'] ?? null,
                'unite'                => $data['unite'] ?? null,
                'poste'                => $data['poste'] ?? null,
                'chef_hierarchique_id' => $data['chef_hierarchique_id'] ?? null,
                'status'               => $data['status'] ?? 'active',
                'password'             => Hash::make($data['password'] ?? 'password'),
            ]
        );

        $roleIds = Role::whereIn('name', $roleNames)->pluck('id')->all();

        // pivot a timestamps, mais nullable => OK même sans withTimestamps()
        $user->roles()->sync($roleIds);

        return $user;
    }

    public function run(): void
    {
        $pwd = 'password';

        // ✅ ADMIN (seul vrai “super user”)
        $admin = $this->upsertUser([
            'matricule' => 'AD2M-ADMIN',
            'nom'       => 'Administrateur',
            'name'      => 'Admin AD2M',
            'email'     => 'admin@ad2m.com',
            'telephone' => '0340000000',
            'unite'     => 'Direction',
            'poste'     => 'Administrateur',
            'password'  => $pwd,
            'chef_hierarchique_id' => null,
        ], ['administrateur','admin']);

        // ✅ 2 CH (pas de chef)
        $ch1 = $this->upsertUser([
            'matricule' => 'CH-001',
            'nom'       => 'Chef',
            'name'      => 'CH Nord',
            'email'     => 'ch1@ad2m.com',
            'telephone' => '0340000010',
            'unite'     => 'Opérations',
            'poste'     => "Chef Hiérarchique",
            'password'  => $pwd,
            'chef_hierarchique_id' => null,
        ], ['chef_hierarchique']);

        $ch2 = $this->upsertUser([
            'matricule' => 'CH-002',
            'nom'       => 'Chef',
            'name'      => 'CH Sud',
            'email'     => 'ch2@ad2m.com',
            'telephone' => '0340000011',
            'unite'     => 'Opérations',
            'poste'     => "Chef Hiérarchique",
            'password'  => $pwd,
            'chef_hierarchique_id' => null,
        ], ['chef_hierarchique']);

        // ✅ RAF / CP / ACCP (pas de chef, règle simple)
        $raf = $this->upsertUser([
            'matricule' => 'RAF-001',
            'nom'       => 'Responsable',
            'name'      => 'RAF Finance',
            'email'     => 'raf@ad2m.com',
            'telephone' => '0340000003',
            'unite'     => 'Finance',
            'poste'     => 'RAF',
            'password'  => $pwd,
            'chef_hierarchique_id' => null,
        ], ['raf']);

        $cp = $this->upsertUser([
            'matricule' => 'CP-001',
            'nom'       => 'Coordonnateur',
            'name'      => 'Coordonnateur Projet',
            'email'     => 'cp@ad2m.com',
            'telephone' => '0340000004',
            'unite'     => 'Projet',
            'poste'     => 'CP',
            'password'  => $pwd,
            'chef_hierarchique_id' => null,
        ], ['coordonnateur_de_projet']);

        $accp = $this->upsertUser([
            'matricule' => 'ACCP-001',
            'nom'       => 'Agent',
            'name'      => 'ACCP Paiement',
            'email'     => 'accp@ad2m.com',
            'telephone' => '0340000005',
            'unite'     => 'Compta',
            'poste'     => 'ACCP',
            'password'  => $pwd,
            'chef_hierarchique_id' => null,
        ], ['accp']);

        // ✅ 2 missionnaires (chacun a son CH)
        $this->upsertUser([
            'matricule' => 'M-001',
            'nom'       => 'Missionnaire',
            'name'      => 'Missionnaire 1',
            'email'     => 'm1@ad2m.com',
            'telephone' => '0341000001',
            'unite'     => 'Opérations',
            'poste'     => 'Agent terrain',
            'password'  => $pwd,
            'chef_hierarchique_id' => $ch1->id,
        ], ['missionnaire']);

        $this->upsertUser([
            'matricule' => 'M-002',
            'nom'       => 'Missionnaire',
            'name'      => 'Missionnaire 2',
            'email'     => 'm2@ad2m.com',
            'telephone' => '0341000002',
            'unite'     => 'Opérations',
            'poste'     => 'Agent terrain',
            'password'  => $pwd,
            'chef_hierarchique_id' => $ch2->id,
        ], ['missionnaire']);
    }
}
