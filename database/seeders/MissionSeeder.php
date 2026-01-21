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
        $accp = User::where('email', 'accp@example.com')->first();

        $missionnaires = User::whereHas('roles', fn($q) => $q->where('name', 'missionnaire'))->get();
        if ($missionnaires->isEmpty()) return;

        $destinations = ['Morondava','Toliara','Toamasina','Antsirabe','Mahajanga'];
        $deplacements = ['4x4','Bus','Avion','Moto','Bateau'];

        $statuses = [
            'brouillon',
            'en_attente_ch',
            'valide_ch',
            'valide_raf',
            'valide_cp',
            'avance_payee',
            'en_cours',
            'cloturee',
        ];

        $i = 1;
        foreach ($missionnaires->take(12) as $u) {
            foreach ($statuses as $s) {
                $dest = $destinations[$i % count($destinations)];
                $mov  = $deplacements[$i % count($deplacements)];
                $start = now()->addDays($i)->toDateString();
                $end   = now()->addDays($i + 2)->toDateString();

                $data = [
                    'demandeur_id' => $u->id,
                    'objet' => "Mission test {$s} #{$i}",
                    'destination' => $dest,
                    'moyen_deplacement' => $mov,
                    'motif' => "Motif test pour statut {$s}",
                    'date_debut' => $start,
                    'date_fin' => $end,
                    'montant_avance_demande' => 100000 + ($i * 5000),
                    'statut_actuel' => $s,

                    // par défaut
                    'validation_ch_id' => null,
                    'validation_raf_id' => null,
                    'validation_cp_id' => null,
                    'pj_regularise' => false,
                    'date_pj_regularise' => null,
                    'note_regularisation' => null,

                    'montant_total_justifie' => null,
                    'reliquat_a_rembourser' => null,
                ];

                // ✅ remplir validations selon statut
                if (in_array($s, ['valide_ch','valide_raf','valide_cp','avance_payee','en_cours','cloturee'])) {
                    $data['validation_ch_id'] = $ch?->id;
                }
                if (in_array($s, ['valide_raf','valide_cp','avance_payee','en_cours','cloturee'])) {
                    $data['validation_raf_id'] = $raf?->id;
                }
                if (in_array($s, ['valide_cp','avance_payee','en_cours','cloturee'])) {
                    $data['validation_cp_id'] = $cp?->id;
                }

                // ✅ si cloturée : PJ régularisées = true (logique)
                if ($s === 'cloturee') {
                    $data['pj_regularise'] = true;
                    $data['date_pj_regularise'] = now()->subDays(1);
                    $data['note_regularisation'] = 'PJ OK (seed)';
                    $data['montant_total_justifie'] = $data['montant_avance_demande'];
                    $data['reliquat_a_rembourser'] = 0;
                }

                Mission::create($data);

                $i++;
            }
        }
    }
}
