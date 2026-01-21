<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class WorkflowController extends Controller
{
    /**
     * BROUILLON → EN ATTENTE CH
     */
    public function soumettre(Request $request, Mission $mission)
    {
        if ($mission->statut_actuel !== 'brouillon') {
            return response()->json(['message' => 'Statut invalide'], 422);
        }

        if ($mission->demandeur_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $mission->update(['statut_actuel' => 'en_attente_ch']);

        return response()->json(['message' => 'Mission soumise']);
    }

    /**
     * EN ATTENTE CH → VALIDE CH
     */
    public function validerCH(Request $request, Mission $mission)
    {
        if (!$request->user()->hasRole(['chef_hierarchique','admin','administrateur'])) {
            return response()->json(['message' => 'Rôle CH requis'], 403);
        }

        if ($mission->statut_actuel !== 'en_attente_ch') {
            return response()->json(['message' => 'Statut invalide'], 422);
        }

        $mission->update([
            'statut_actuel' => 'valide_ch',
            'validation_ch_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Validée CH']);
    }

    /**
     * VALIDE CH → VALIDE RAF
     */
    public function validerRAF(Request $request, Mission $mission)
    {
        if (!$request->user()->hasRole(['raf','admin','administrateur'])) {
            return response()->json(['message' => 'Rôle RAF requis'], 403);
        }

        if ($mission->statut_actuel !== 'valide_ch') {
            return response()->json(['message' => 'Statut invalide'], 422);
        }

        $mission->update([
            'statut_actuel' => 'valide_raf',
            'validation_raf_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Validée RAF']);
    }

    /**
     * VALIDE RAF → VALIDE CP
     */
    public function validerCP(Request $request, Mission $mission)
    {
        if (!$request->user()->hasRole(['coordonnateur_de_projet','admin','administrateur'])) {
            return response()->json(['message' => 'Rôle CP requis'], 403);
        }

        if ($mission->statut_actuel !== 'valide_raf') {
            return response()->json(['message' => 'Statut invalide'], 422);
        }

        $mission->update([
            'statut_actuel' => 'valide_cp',
            'validation_cp_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Validée CP']);
    }

    /**
     * VALIDE CP → AVANCE PAYÉE
     * ✅ On force l'existence d'une avance "paiement"
     */
    public function payer(Request $request, Mission $mission)
    {
        if (!$request->user()->hasRole(['accp','admin','administrateur'])) {
            return response()->json(['message' => 'Rôle ACCP requis'], 403);
        }

        if ($mission->statut_actuel !== 'valide_cp') {
            return response()->json(['message' => 'Statut invalide'], 422);
        }

        $hasPaiement = $mission->avances()
            ->where('type_operation', 'paiement')
            ->exists();

        if (!$hasPaiement) {
            return response()->json([
                'message' => "Impossible : enregistrez d'abord l'avance (type 'paiement') avant de confirmer."
            ], 422);
        }

        $mission->update(['statut_actuel' => 'avance_payee']);

        return response()->json(['message' => 'Avance payée']);
    }

    /**
     * AVANCE PAYÉE → EN COURS (action missionnaire)
     */
    public function commencer(Request $request, Mission $mission)
    {
        if ($mission->statut_actuel !== 'avance_payee') {
            return response()->json(['message' => 'Statut invalide'], 422);
        }

        if ($mission->demandeur_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $mission->update(['statut_actuel' => 'en_cours']);

        return response()->json(['message' => 'Mission en cours']);
    }

    /**
     * Régulariser PJ (ACCP)
     */
    public function regulariserPJ(Request $request, Mission $mission)
    {
        $request->validate([
            'note_regularisation' => 'nullable|string|max:5000',
        ]);

        if (!$request->user()->hasRole(['accp', 'admin', 'administrateur'])) {
            return response()->json(['message' => 'Rôle ACCP requis'], 403);
        }

        if (!in_array($mission->statut_actuel, ['avance_payee', 'en_cours'], true)) {
            return response()->json(['message' => 'Statut invalide'], 422);
        }

        $mission->update([
            'pj_regularise' => true,
            'date_pj_regularise' => now(),
            'note_regularisation' => $request->input('note_regularisation'),
        ]);

        return response()->json(['message' => 'PJ régularisées']);
    }

    /**
     * EN COURS → CLOTURÉE (ACCP)
     * ✅ Bloqué si PJ non régularisées
     */
    public function cloturer(Request $request, Mission $mission)
    {
        if (!$request->user()->hasRole(['accp','admin','administrateur'])) {
            return response()->json(['message' => 'Rôle ACCP requis'], 403);
        }

        if ($mission->statut_actuel !== 'en_cours') {
            return response()->json(['message' => 'Statut invalide'], 422);
        }

        if (!$mission->pj_regularise) {
            return response()->json(['message' => 'Régularisez les PJ avant clôture.'], 422);
        }

        $data = ['statut_actuel' => 'cloturee'];

        // ✅ évite erreur SQL si la colonne n'existe pas encore
        if (Schema::hasColumn('missions', 'date_cloture')) {
            $data['date_cloture'] = now();
        }

        $mission->update($data);

        return response()->json(['message' => 'Mission clôturée']);
    }

    /**
     * REJET → RETOUR BROUILLON
     */
    public function rejeter(Request $request, Mission $mission)
    {
        if (in_array($mission->statut_actuel, ['avance_payee','en_cours','cloturee'], true)) {
            return response()->json(['message' => 'Mission déjà engagée'], 422);
        }

        if (!$request->user()->hasRole(['chef_hierarchique','raf','coordonnateur_de_projet','admin','administrateur'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $mission->update(['statut_actuel' => 'brouillon']);

        return response()->json(['message' => 'Mission rejetée']);
    }
}
