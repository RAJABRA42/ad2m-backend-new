<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Liste des rôles
     */
    public function roles()
    {
        $roles = Role::query()
            ->select(['id', 'name', 'display_name'])
            ->orderBy('display_name')
            ->get();

        return response()->json(['roles' => $roles]);
    }

    /**
     * Liste des CH + missionnaires (avec leur chef actuel)
     */
    public function hierarchy()
    {
        $chs = User::query()
            ->select(['id', 'name', 'matricule', 'email'])
            ->whereHas('roles', fn ($q) => $q->where('name', 'chef_hierarchique'))
            ->orderBy('name')
            ->get();

        $missionnaires = User::query()
            ->select(['id', 'name', 'matricule', 'email', 'unite', 'poste', 'chef_hierarchique_id', 'status'])
            ->with(['chef:id,name,matricule'])
            ->whereHas('roles', fn ($q) => $q->where('name', 'missionnaire'))
            ->orderBy('name')
            ->get();

        return response()->json([
            'chs' => $chs,
            'missionnaires' => $missionnaires,
        ]);
    }

    /**
     * Liste des utilisateurs (pour administration)
     */
    public function usersIndex()
    {
        $users = User::query()
            ->select([
                'id', 'matricule', 'nom', 'name', 'email',
                'telephone', 'unite', 'poste',
                'chef_hierarchique_id', 'status', 'created_at'
            ])
            ->with([
                'roles:id,name,display_name',
                'chef:id,name,matricule'
            ])
            ->orderByDesc('id')
            ->get();

        return response()->json(['users' => $users]);
    }

    /**
     * Création utilisateur + 1 rôle + chef si missionnaire
     */
    public function usersStore(Request $request)
    {
        $data = $request->validate([
            'matricule' => 'required|string|max:50|unique:users,matricule',
            'nom'       => 'required|string|max:150',
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'telephone' => 'nullable|string|max:50',
            'unite'     => 'nullable|string|max:100',
            'poste'     => 'nullable|string|max:100',
            'password'  => 'required|string|min:6',
            'status'    => 'nullable|in:active,inactive',

            // rôle unique
            'role'      => 'required|string|exists:roles,name',

            // chef requis seulement si missionnaire
            'chef_hierarchique_id' => 'nullable|integer|exists:users,id',
        ]);

        $roleName = strtolower($data['role']);

        // ✅ règles chef
        if ($roleName === 'missionnaire') {
            if (empty($data['chef_hierarchique_id'])) {
                return response()->json(['message' => 'Un missionnaire doit avoir un Chef hiérarchique.'], 422);
            }

            $isCH = User::query()
                ->whereKey($data['chef_hierarchique_id'])
                ->whereHas('roles', fn ($q) => $q->where('name', 'chef_hierarchique'))
                ->exists();

            if (!$isCH) {
                return response()->json(['message' => 'Le chef sélectionné doit avoir le rôle Chef hiérarchique.'], 422);
            }
        } else {
            // ton modèle: CH/RAF/CP/ACCP/Admin => pas de chef
            $data['chef_hierarchique_id'] = null;
        }

        $user = User::create([
            'matricule'            => $data['matricule'],
            'nom'                  => $data['nom'],
            'name'                 => $data['name'],
            'email'                => $data['email'],
            'telephone'            => $data['telephone'] ?? null,
            'unite'                => $data['unite'] ?? null,
            'poste'                => $data['poste'] ?? null,
            'chef_hierarchique_id' => $data['chef_hierarchique_id'] ?? null,
            'password'             => Hash::make($data['password']),
            'status'               => $data['status'] ?? 'active',
        ]);

        $role = Role::where('name', $roleName)->first();
        $now = now();

        $user->roles()->sync([
            $role->id => ['created_at' => $now, 'updated_at' => $now]
        ]);

        return response()->json([
            'message' => 'Utilisateur créé.',
            'user' => $user->fresh(['roles:id,name,display_name', 'chef:id,name,matricule']),
        ], 201);
    }

    /**
     * Mise à jour user (infos + statut + rôle + chef selon rôle)
     */
    public function usersUpdate(Request $request, User $user)
    {
        $data = $request->validate([
            'matricule' => 'sometimes|required|string|max:50|unique:users,matricule,' . $user->id,
            'nom'       => 'sometimes|required|string|max:150',
            'name'      => 'sometimes|required|string|max:255',
            'email'     => 'sometimes|required|email|unique:users,email,' . $user->id,
            'telephone' => 'nullable|string|max:50',
            'unite'     => 'nullable|string|max:100',
            'poste'     => 'nullable|string|max:100',
            'status'    => 'sometimes|required|in:active,inactive',

            'password'  => 'nullable|string|min:6',
            'role'      => 'sometimes|required|string|exists:roles,name',
            'chef_hierarchique_id' => 'nullable|integer|exists:users,id',
        ]);

        $currentRole = strtolower($user->roles()->first()?->name ?? '');
        $roleName = array_key_exists('role', $data) ? strtolower($data['role']) : $currentRole;

        // ✅ règles chef selon rôle
        if ($roleName === 'missionnaire') {
            $chefId = $data['chef_hierarchique_id'] ?? $user->chef_hierarchique_id;

            if (!$chefId) {
                return response()->json(['message' => 'Un missionnaire doit avoir un Chef hiérarchique.'], 422);
            }

            $isCH = User::query()
                ->whereKey($chefId)
                ->whereHas('roles', fn ($q) => $q->where('name', 'chef_hierarchique'))
                ->exists();

            if (!$isCH) {
                return response()->json(['message' => 'Le chef sélectionné doit avoir le rôle Chef hiérarchique.'], 422);
            }

            $data['chef_hierarchique_id'] = $chefId;
        } else {
            $data['chef_hierarchique_id'] = null;
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        if (array_key_exists('role', $data)) {
            $role = Role::where('name', $roleName)->first();
            $now = now();
            $user->roles()->sync([
                $role->id => ['created_at' => $now, 'updated_at' => $now]
            ]);
        }

        return response()->json([
            'message' => 'Utilisateur mis à jour.',
            'user' => $user->fresh(['roles:id,name,display_name', 'chef:id,name,matricule']),
        ]);
    }

    /**
     * Affecter CH (route rapide depuis l'onglet "Affectations")
     */
    public function updateChef(Request $request, User $user)
    {
        if (!$user->roles()->where('name', 'missionnaire')->exists()) {
            return response()->json(['message' => 'Seuls les missionnaires peuvent avoir un chef.'], 422);
        }

        $data = $request->validate([
            'chef_hierarchique_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);

        $chefId = (int) $data['chef_hierarchique_id'];

        $isCH = User::query()
            ->whereKey($chefId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'chef_hierarchique'))
            ->exists();

        if (!$isCH) {
            return response()->json(['message' => 'Le chef doit avoir le rôle CH.'], 422);
        }

        $user->update(['chef_hierarchique_id' => $chefId]);

        return response()->json([
            'message' => 'Chef mis à jour.',
            'user' => $user->fresh(['chef:id,name,matricule']),
        ]);
    }
}
