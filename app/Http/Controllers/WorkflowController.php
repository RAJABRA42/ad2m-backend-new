<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use Illuminate\Http\Request;

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
            'validation_ch_id' => $request->user()->id
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
            'validation_raf_id' => $request->user()->id
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
            'validation_cp_id' => $request->user()->id
        ]);

        return response()->json(['message' => 'Validée CP']);
    }

    /**
     * VALIDE CP → AVANCE PAYÉE
     */
    public function payer(Request $request, Mission $mission)
    {
        if (!$request->user()->hasRole(['accp','admin','administrateur'])) {
            return response()->json(['message' => 'Rôle ACCP requis'], 403);
        }

        if ($mission->statut_actuel !== 'valide_cp') {
            return response()->json(['message' => 'Statut invalide'], 422);
        }

        $mission->update(['statut_actuel' => 'avance_payee']);

        return response()->json(['message' => 'Avance payée']);
    }

    /**
     * AVANCE PAYÉE → EN COURS
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
     * EN COURS → CLOTURÉE
     */
    public function cloturer(Request $request, Mission $mission)
    {
        if (!$request->user()->hasRole(['accp','admin','administrateur'])) {
            return response()->json(['message' => 'Rôle ACCP requis'], 403);
        }

        if ($mission->statut_actuel !== 'en_cours') {
            return response()->json(['message' => 'Statut invalide'], 422);
        }

        $mission->update([
            'statut_actuel' => 'cloturee',
            'date_cloture' => now()
        ]);

        return response()->json(['message' => 'Mission clôturée']);
    }

    /**
     * REJET → RETOUR BROUILLON
     */
    public function rejeter(Request $request, Mission $mission)
    {
        if (in_array($mission->statut_actuel, ['avance_payee','en_cours','cloturee'])) {
            return response()->json(['message' => 'Mission déjà engagée'], 422);
        }

        if (!$request->user()->hasRole(['chef_hierarchique','raf','coordonnateur_de_projet','admin'])) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $mission->update(['statut_actuel' => 'brouillon']);

        return response()->json(['message' => 'Mission rejetée']);
    }
}
