<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MissionController extends Controller
{
    /**
     * LISTE DES MISSIONS
     * - Décideurs : toutes
     * - Missionnaire : ses missions
     */
    public function index(Request $request)
    {
        $user = $request->user()->load('roles');

        $query = Mission::with(['demandeur:id,name']);

        $isDecisionMaker = $user->hasRole([
            'admin',
            'administrateur',
            'raf',
            'accp',
            'chef_hierarchique',
            'coordonnateur_de_projet'
        ]);

        if (!$isDecisionMaker) {
            $query->where('demandeur_id', $user->id);
        }

        $missions = $query->orderByDesc('id')->get();

        return response()->json([
            'missions' => $missions
        ]);
    }

    /**
     * DETAILS D'UNE MISSION
     */
    public function show(Request $request, Mission $mission)
    {
        $user = $request->user()->load('roles');

        $isOwner = $mission->demandeur_id === $user->id;
        $isStaff = $user->hasRole([
            'admin','administrateur','raf','accp','chef_hierarchique','coordonnateur_de_projet'
        ]);

        if (!$isOwner && !$isStaff) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $mission->load('demandeur:id,name');

        return response()->json([
            'mission' => $mission
        ]);
    }

    /**
     * CREER UNE MISSION (BROUILLON)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'objet' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'motif' => 'nullable|string',
            'date_depart' => 'nullable|date',
            'date_retour' => 'nullable|date|after_or_equal:date_depart',
            'montant_avance' => 'nullable|numeric|min:0',
        ]);

        $data['demandeur_id'] = Auth::id();
        $data['statut_actuel'] = 'brouillon';

        try {
            $mission = Mission::create($data);

            return response()->json([
                'message' => 'Mission créée',
                'mission' => $mission
            ], 201);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'Erreur création mission'], 500);
        }
    }

    /**
     * MODIFIER UNE MISSION (BROUILLON UNIQUEMENT)
     */
    public function update(Request $request, Mission $mission)
    {
        $user = $request->user()->load('roles');

        if (
            !$user->hasRole(['admin','administrateur']) &&
            ($mission->demandeur_id !== $user->id || $mission->statut_actuel !== 'brouillon')
        ) {
            return response()->json(['message' => 'Modification interdite'], 403);
        }

        $data = $request->validate([
            'objet' => 'sometimes|required|string|max:255',
            'destination' => 'sometimes|required|string|max:255',
            'motif' => 'nullable|string',
            'date_depart' => 'nullable|date',
            'date_retour' => 'nullable|date|after_or_equal:date_depart',
            'montant_avance' => 'nullable|numeric|min:0',
        ]);

        $mission->update($data);

        return response()->json([
            'message' => 'Mission mise à jour',
            'mission' => $mission
        ]);
    }

    /**
     * SUPPRIMER UNE MISSION
     */
    public function destroy(Request $request, Mission $mission)
    {
        $user = $request->user()->load('roles');

        $canDelete =
            $user->hasRole(['admin','administrateur']) ||
            ($mission->demandeur_id === $user->id && $mission->statut_actuel === 'brouillon');

        if (!$canDelete) {
            return response()->json(['message' => 'Suppression interdite'], 403);
        }

        $mission->delete();

        return response()->json(['message' => 'Mission supprimée']);
    }
}
