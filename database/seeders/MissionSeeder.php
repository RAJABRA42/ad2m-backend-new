<?php

namespace Database\Seeders;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Database\Seeder;

class MissionSeeder extends Seeder
{
    public function run(): void
    {
        $ch   = User::where('email', 'ch@example.com')->first();
        $raf  = User::where('email', 'raf@example.com')->first();
        $cp   = User::where('email', 'cp@example.com')->first();

        $missionnaires = User::whereHas('roles', fn($q) => $q->where('name', 'missionnaire'))->get();
        if ($missionnaires->isEmpty()) return;

        $destinations = ['Morondava','Toliara','Toamasina','Antsirabe','Mahajanga'];
        $moves        = ['4x4','Bus','Avion','Moto'];

        // On crée 2 missions par missionnaire
        $i = 0;
        foreach ($missionnaires as $u) {
            for ($j = 1; $j <= 2; $j++) {
                $i++;

                $status = match ($j) {
                    1 => 'brouillon',
                    2 => 'avance_payee',
                };

                $start = now()->addDays($i)->toDateString();
                $end   = now()->addDays($i + 2)->toDateString();

                $data = [
                    'demandeur_id' => $u->id,
                    'objet' => "Mission test #{$i}",
                    'destination' => $destinations[$i % count($destinations)],
                    'moyen_deplacement' => $moves[$i % count($moves)],
                    'motif' => "Contexte test mission #{$i}",
                    'date_debut' => $start,
                    'date_fin' => $end,
                    'montant_avance_demande' => 150000 + ($i * 5000),
                    'statut_actuel' => $status,

                    // validations (on laisse simple)
                    'validation_ch_id' => null,
                    'validation_raf_id' => null,
                    'validation_cp_id' => null,

                    

                   
                ];

                // si avance_payee => on considère validé (simple pour tester)
                if ($status === 'avance_payee') {
                    $data['validation_ch_id'] = $ch?->id;
                    $data['validation_raf_id'] = $raf?->id;
                    $data['validation_cp_id'] = $cp?->id;
                }

                Mission::updateOrCreate(
                    ['demandeur_id' => $u->id, 'objet' => $data['objet']],
                    $data
                );
            }
        }
    }
}
