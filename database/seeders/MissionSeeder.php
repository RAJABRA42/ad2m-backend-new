<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Mission;

class MissionSeeder extends Seeder
{
    public function run(): void
    {
        $missionnaire = User::where('email', 'user@example.com')->first();
        if (!$missionnaire) return;

        Mission::create([
            'demandeur_id' => $missionnaire->id,
            'objet' => 'Mission test - Collecte terrain',
            'destination' => 'Morondava',
            'motif' => 'Collecte de données',
            'moyen_deplacement' => '4x4',
            'date_debut' => now()->toDateString(),
            'date_fin' => now()->addDays(2)->toDateString(),
            'montant_avance_demande' => 250000,
            'statut_actuel' => 'brouillon',
        ]);
    }
}
