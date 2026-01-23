<?php

namespace App\Http\Controllers;

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
     * ✅ Correction : un CH ne voit que les missions qui lui sont assignées (inbox)
     */
    public function index(Request $request)
    {
        $user = $request->user()->load('roles');

        $query = Mission::with(['demandeur:id,name,matricule']);

        $isDecisionMaker = $user->hasRole([
            'admin',
            'administrateur',
            'raf',
            'accp',
            'chef_hierarchique',
            'coordonnateur_de_projet'
        ]);

        $mine = $request->boolean('mine');
        $isAdmin = $user->hasRole(['admin', 'administrateur']);

        
       // (missions assignées) OU (missions déjà traitées par lui)
        if ($user->hasRole('chef_hierarchique') && !$isAdmin && !$mine) {
                $query->where(function ($q) use ($user) {
                    $q->where('chef_hierarchique_id', $user->id)
                    ->orWhere('validation_ch_id', $user->id);
                });
            }

        // comportement existant : mes missions si mine=true ou si non-décideur
        elseif ($mine || !$isDecisionMaker) {
            $query->where('demandeur_id', $user->id);
        }

        $missions = $query->orderByDesc('id')->get();

        return response()->json([
            'missions' => $missions
        ]);
    }

    /**
     * DETAILS D'UNE MISSION
     * ✅ Correction : CH ne peut ouvrir que ses missions (demandeur) ou celles assignées à lui
     */
    public function show(Request $request, Mission $mission)
    {
        $user = $request->user()->load('roles');

        // missionnaire ne voit que ses missions
        if ($user->hasRole(['missionnaire']) && (int)$mission->demandeur_id !== (int)$user->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        
        $isAdmin = $user->hasRole(['admin', 'administrateur']);
        
        if ($user->hasRole('chef_hierarchique') && !$isAdmin) {
            $isOwner    = (int)$mission->demandeur_id === (int)$user->id;
            $isAssigned = (int)($mission->chef_hierarchique_id ?? 0) === (int)$user->id;
            $isHandled  = (int)($mission->validation_ch_id ?? 0) === (int)$user->id; 

            if (!$isOwner && !$isAssigned && !$isHandled) {
                return response()->json(['message' => 'Introuvable'], 404);
            }
        }

        $mission->load([
            'demandeur',
            'avances' => function ($q) {
                $q->orderByDesc('date_operation')->with('executedBy');
            },
        ]);

        return response()->json(['mission' => $mission]);
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
            'moyen_deplacement' => 'nullable|string|max:100',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'montant_avance_demande' => 'nullable|numeric|min:0',
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
            ((int)$mission->demandeur_id !== (int)$user->id || $mission->statut_actuel !== 'brouillon')
        ) {
            return response()->json(['message' => 'Modification interdite'], 403);
        }

        $data = $request->validate([
            'objet' => 'sometimes|required|string|max:255',
            'destination' => 'sometimes|required|string|max:255',
            'motif' => 'nullable|string',
            'moyen_deplacement' => 'nullable|string|max:100',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'montant_avance_demande' => 'nullable|numeric|min:0',
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
            ((int)$mission->demandeur_id === (int)$user->id && $mission->statut_actuel === 'brouillon');

        if (!$canDelete) {
            return response()->json(['message' => 'Suppression interdite'], 403);
        }

        $mission->delete();

        return response()->json(['message' => 'Mission supprimée']);
    }
}
