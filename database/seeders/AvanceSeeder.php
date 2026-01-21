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

        $missions = Mission::whereIn('statut_actuel', ['avance_payee','en_cours','cloturee'])->get();

        foreach ($missions as $m) {
            $exists = Avance::where('mission_id', $m->id)
                ->where('type_operation', 'paiement')
                ->exists();

            if ($exists) continue;

            Avance::create([
                'mission_id' => $m->id,
                'executed_by_id' => $accp->id,
                'montant' => (float)($m->montant_avance_demande ?? 0),
                'type_operation' => 'paiement',
                'date_operation' => now()->subDays(rand(1, 10)),
                'numero_piece_paiement' => 'BR-SEED-' . $m->id,
            ]);
        }
    }
}
