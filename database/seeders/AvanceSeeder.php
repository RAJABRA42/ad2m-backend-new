<?php

namespace Database\Seeders;

use App\Models\Avance;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Database\Seeder;

class AvanceSeeder extends Seeder
{
    public function run(): void
    {
        $accp = User::where('email', 'accp@example.com')->first();
        if (!$accp) return;

        $missions = Mission::where('statut_actuel', 'avance_payee')->get();

        foreach ($missions as $m) {
            Avance::updateOrCreate(
                [
                    'mission_id' => $m->id,
                    'type_operation' => 'paiement',
                ],
                [
                    'executed_by_id' => $accp->id,
                    'montant' => (float)($m->montant_avance_demande ?? 0),
                    'date_operation' => now()->subDays(2),
                    'numero_piece_paiement' => 'BR-TEST-' . $m->id,
                ]
            );
        }
    }
}
