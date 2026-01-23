<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class WorkflowController extends Controller
{
    /**
     * BROUILLON → EN ATTENTE CH
     * ✅ Correction : assigner automatiquement le CH du missionnaire
     */
    public function soumettre(Request $request, Mission $mission)
{
    $user = $request->user()->load('roles');

    if ($mission->statut_actuel !== 'brouillon') {
        return response()->json(['message' => 'Statut invalide'], 422);
    }

    if ((int)$mission->demandeur_id !== (int)$user->id) {
        return response()->json(['message' => 'Accès refusé'], 403);
    }

    // ✅ CH n’a pas de chef => CH -> RAF
    if ($user->hasRole('chef_hierarchique')) {
        $mission->update([
            'statut_actuel' => 'valide_ch',        // inbox RAF
            'validation_ch_id' => $user->id,       // étape CH considérée OK
            'chef_hierarchique_id' => null,
        ]);
        return response()->json(['message' => 'Mission soumise (vers RAF)']);
    }

    // ✅ RAF -> CP
    if ($user->hasRole('raf')) {
        $mission->update([
            'statut_actuel' => 'valide_raf',       // inbox CP
            'validation_raf_id' => $user->id,      // budget ok (auto)
            'chef_hierarchique_id' => null,
        ]);
        return response()->json(['message' => 'Mission soumise (vers CP)']);
    }

    // ✅ CP -> RAF
    if ($user->hasRole('coordonnateur_de_projet')) {
        $mission->update([
            'statut_actuel' => 'valide_ch',        // inbox RAF
            'validation_cp_id' => $user->id,       // étape CP considérée OK
            'chef_hierarchique_id' => null,
        ]);
        return response()->json(['message' => 'Mission soumise (vers RAF)']);
    }

    // ✅ ACCP -> RAF
    if ($user->hasRole('accp')) {
        $mission->update([
            'statut_actuel' => 'valide_ch',        // inbox RAF
            'chef_hierarchique_id' => null,
        ]);
        return response()->json(['message' => 'Mission soumise (vers RAF)']);
    }

    // ✅ Missionnaire normal -> CH (assigné)
    $chefId = $user->chef_hierarchique_id;
    if (!$chefId) {
        return response()->json(['message' => "Aucun chef hiérarchique assigné à votre profil."], 422);
    }

    $mission->update([
        'statut_actuel' => 'en_attente_ch',       // inbox CH
        'chef_hierarchique_id' => $chefId,
    ]);

    return response()->json(['message' => 'Mission soumise (vers CH)']);
}


    /**
     * EN ATTENTE CH → VALIDE CH
     * ✅ Correction : seul le CH assigné peut valider (sinon 404)
     */
    public function validerCH(Request $request, Mission $mission)
    {
        $user = $request->user()->load('roles');

        if (!$user->hasRole(['chef_hierarchique','admin','administrateur'])) {
            return response()->json(['message' => 'Rôle CH requis'], 403);
        }

        $isAdmin = $user->hasRole(['admin','administrateur']);

        if ($user->hasRole('chef_hierarchique') && !$isAdmin) {
            if ((int)$mission->chef_hierarchique_id !== (int)$user->id) {
                return response()->json(['message' => 'Introuvable'], 404);
            }
        }

        if ($mission->statut_actuel !== 'en_attente_ch') {
            return response()->json(['message' => 'Statut invalide'], 422);
        }

        $mission->update([
            'statut_actuel' => 'valide_ch',
            'validation_ch_id' => $user->id,
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

    // le demandeur est CP => CP -> RAF -> ACCP (on saute CP après RAF)
    $demandeur = $mission->demandeur()->with('roles')->first();

    $nextStatus = ($demandeur && $demandeur->hasRole('coordonnateur_de_projet'))
        ? 'valide_cp'   // inbox ACCP
        : 'valide_raf'; // inbox CP 

    $mission->update([
        'statut_actuel' => $nextStatus,
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

        if ((int)$mission->demandeur_id !== (int)$request->user()->id) {
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
     * ✅ Correction : si CH, il ne peut rejeter que ses missions assignées
     */
    public function rejeter(Request $request, Mission $mission)
{
    $user = $request->user()->load('roles');

    if (in_array($mission->statut_actuel, ['avance_payee','en_cours','cloturee'], true)) {
        return response()->json(['message' => 'Mission déjà engagée'], 422);
    }

    if (!$user->hasRole(['chef_hierarchique','raf','coordonnateur_de_projet','admin','administrateur'])) {
        return response()->json(['message' => 'Accès refusé'], 403);
    }

    $isAdmin = $user->hasRole(['admin','administrateur']);

    if ($user->hasRole('chef_hierarchique') && !$isAdmin) {
        // garde ton contrôle actuel
        if ((int)($mission->chef_hierarchique_id ?? 0) !== (int)$user->id) {
            return response()->json(['message' => 'Introuvable'], 404);
        }
    }

    $from = $mission->statut_actuel;

    $data = [
        'statut_actuel' => 'brouillon', // retour missionnaire
    ];

    if ($from === 'en_attente_ch') {
        $data['validation_ch_id'] = $user->id;
    } elseif ($from === 'valide_ch') {
        $data['validation_raf_id'] = $user->id;
    } elseif ($from === 'valide_raf') {
        $data['validation_cp_id'] = $user->id;
    }

    $mission->update($data);

    return response()->json([
        'message' => 'Mission rejetée',
        'mission' => $mission->fresh(),
    ]);
}













}
