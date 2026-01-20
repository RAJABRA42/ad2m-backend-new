<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mission;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load('roles');

        // Même logique que MissionController@index
        $isDecisionMaker = $user->hasRole([
            'admin',
            'administrateur',
            'raf',
            'accp',
            'chef_hierarchique',
            'coordonnateur_de_projet',
        ]);

        $baseQuery = Mission::query();

        if (!$isDecisionMaker) {
            $baseQuery->where('demandeur_id', $user->id);
        }

        // Stats par statut (1 requête)
        $counts = (clone $baseQuery)
            ->selectRaw('statut_actuel, COUNT(*) as total')
            ->groupBy('statut_actuel')
            ->pluck('total', 'statut_actuel');

        $statuts = [
            'brouillon',
            'en_attente_ch',
            'valide_ch',
            'valide_raf',
            'valide_cp',
            'avance_payee',
            'en_cours',
            'cloturee',
        ];

        $stats = ['total' => (clone $baseQuery)->count()];
        foreach ($statuts as $s) {
            $stats[$s] = (int) ($counts[$s] ?? 0);
        }

        // Missions récentes (déjà filtrées par rôle)
        $recent = (clone $baseQuery)
            ->with(['demandeur:id,name'])
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        // "À traiter" selon rôle (simple & cohérent avec ton WorkflowController)
        $todoStatuses = [];

        if ($user->hasRole(['chef_hierarchique', 'admin', 'administrateur'])) $todoStatuses[] = 'en_attente_ch';
        if ($user->hasRole(['raf', 'admin', 'administrateur'])) $todoStatuses[] = 'valide_ch';
        if ($user->hasRole(['coordonnateur_de_projet', 'admin', 'administrateur'])) $todoStatuses[] = 'valide_raf';

        // ACCP : payer (valide_cp) + cloturer (en_cours)
        if ($user->hasRole(['accp', 'admin', 'administrateur'])) {
            $todoStatuses[] = 'valide_cp';
            $todoStatuses[] = 'en_cours';
        }

        // Missionnaire : soumettre (brouillon) + commencer (avance_payee)
        if (!$isDecisionMaker) {
            $todoStatuses[] = 'brouillon';
            $todoStatuses[] = 'avance_payee';
        }

        $a_traiter = empty($todoStatuses)
            ? 0
            : (clone $baseQuery)->whereIn('statut_actuel', array_values(array_unique($todoStatuses)))->count();

        return response()->json([
            'me' => $user,
            'stats' => $stats,
            'recent' => $recent,
            'a_traiter' => $a_traiter,
            'todo_statuses' => array_values(array_unique($todoStatuses)),
        ]);
    }
}
