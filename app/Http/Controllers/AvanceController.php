<?php

namespace App\Http\Controllers;

use App\Models\Avance;
use App\Models\Mission;
use Illuminate\Http\Request;

class AvanceController extends Controller
{
    /**
     * Enregistrer une avance de paiement (ACCP) sans changer le statut.
     * Autorisé uniquement quand mission est en 'valide_cp'.
     */
    public function store(Request $request, Mission $mission)
    {
        // ✅ rôle
        if (!$request->user()->hasRole(['accp','admin','administrateur'])) {
            return response()->json(['message' => 'Rôle ACCP requis'], 403);
        }

        // ✅ statut
        if ($mission->statut_actuel !== 'valide_cp') {
            return response()->json(['message' => "Impossible d'enregistrer une avance pour ce statut."], 422);
        }

        $data = $request->validate([
            'montant' => ['required', 'numeric', 'min:0.01'],
            'date_operation' => ['required', 'date'],
            'numero_piece_paiement' => ['nullable', 'string', 'max:100'],
        ]);

        // (Optionnel) bloquer si avance > montant demandé
        if (!is_null($mission->montant_avance_demande) && (float)$data['montant'] > (float)$mission->montant_avance_demande) {
            return response()->json(['message' => "Montant supérieur à l'avance demandée."], 422);
        }

        // (Optionnel) éviter double paiement (1 seule avance de paiement)
        $already = $mission->avances()->where('type_operation', 'paiement')->exists();
        if ($already) {
            return response()->json(['message' => "Une avance de paiement existe déjà pour cette mission."], 422);
        }

        $avance = Avance::create([
            'mission_id' => $mission->id,
            'executed_by_id' => $request->user()->id,
            'montant' => $data['montant'],
            'type_operation' => 'paiement',
            'date_operation' => $data['date_operation'],
            'numero_piece_paiement' => $data['numero_piece_paiement'] ?? null,
        ]);

        return response()->json([
            'message' => 'Avance enregistrée',
            'avance' => $avance,
        ], 201);
    }

    /**
     * (utile plus tard) liste des avances d'une mission
     */
    public function index(Request $request, Mission $mission)
    {
        // accès : ACCP/Admin ou demandeur (à adapter selon ton besoin)
        if (
            !$request->user()->hasRole(['accp','admin','administrateur']) &&
            $mission->demandeur_id !== $request->user()->id
        ) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        return response()->json([
            'avances' => $mission->avances()->with('executedBy:id,name')->latest()->get()
        ]);
    }
}
