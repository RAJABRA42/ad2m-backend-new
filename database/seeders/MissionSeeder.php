<?php

namespace Database\Seeders;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class MissionSeeder extends Seeder
{
    public function run(): void
    {
        $raf = User::where('email', 'raf@example.com')->first();
        $cp  = User::where('email', 'cp@example.com')->first();

        $missionnaires = User::whereHas('roles', fn($q) => $q->where('name', 'missionnaire'))->get();
        if ($missionnaires->isEmpty()) return;

        $hasChefColumn = Schema::hasColumn('missions', 'chef_hierarchique_id');

        $destinations = ['Morondava','Toliara','Toamasina','Antsirabe','Mahajanga'];
        $moves        = ['4x4','Bus','Avion','Moto'];

        $i = 0;
        foreach ($missionnaires as $u) {
            $i++;
            $chefId = $u->chef_hierarchique_id; // CH du missionnaire

            // A) Brouillon
            Mission::updateOrCreate(
                ['demandeur_id' => $u->id, 'objet' => "Brouillon #{$u->matricule}"],
                [
                    'demandeur_id' => $u->id,
                    'objet' => "Brouillon #{$u->matricule}",
                    'destination' => $destinations[$i % count($destinations)],
                    'moyen_deplacement' => $moves[$i % count($moves)],
                    'motif' => "Mission brouillon de {$u->name}",
                    'date_debut' => now()->addDays($i)->toDateString(),
                    'date_fin' => now()->addDays($i + 2)->toDateString(),
                    'montant_avance_demande' => 150000 + ($i * 5000),
                    'statut_actuel' => 'brouillon',
                ]
            );

            // B) Soumise -> en_attente_ch (doit être visible uniquement par SON CH)
            $submitted = [
                'demandeur_id' => $u->id,
                'objet' => "Soumise #{$u->matricule}",
                'destination' => $destinations[($i + 1) % count($destinations)],
                'moyen_deplacement' => $moves[($i + 1) % count($moves)],
                'motif' => "Mission soumise de {$u->name}",
                'date_debut' => now()->addDays($i + 3)->toDateString(),
                'date_fin' => now()->addDays($i + 5)->toDateString(),
                'montant_avance_demande' => 180000 + ($i * 6000),
                'statut_actuel' => 'en_attente_ch',
                'validation_ch_id' => null,
                'validation_raf_id' => null,
                'validation_cp_id' => null,
            ];
            if ($hasChefColumn) {
                $submitted['chef_hierarchique_id'] = $chefId; // <-- clé du fix
            }

            Mission::updateOrCreate(
                ['demandeur_id' => $u->id, 'objet' => $submitted['objet']],
                $submitted
            );

            // C) Avance payée (déjà validée CH/RAF/CP)
            $paid = [
                'demandeur_id' => $u->id,
                'objet' => "Payée #{$u->matricule}",
                'destination' => $destinations[($i + 2) % count($destinations)],
                'moyen_deplacement' => $moves[($i + 2) % count($moves)],
                'motif' => "Mission payée de {$u->name}",
                'date_debut' => now()->subDays(10)->toDateString(),
                'date_fin' => now()->subDays(8)->toDateString(),
                'montant_avance_demande' => 220000 + ($i * 7000),
                'statut_actuel' => 'avance_payee',
                'validation_ch_id' => $chefId,
                'validation_raf_id' => $raf?->id,
                'validation_cp_id' => $cp?->id,
            ];
            if ($hasChefColumn) {
                $paid['chef_hierarchique_id'] = $chefId;
            }

            Mission::updateOrCreate(
                ['demandeur_id' => $u->id, 'objet' => $paid['objet']],
                $paid
            );
        }
    }
}
