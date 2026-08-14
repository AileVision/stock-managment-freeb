<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    /**
     * Display a paginated list of users with their roles.
     */
    public function index(): Response
    {
        $users = User::query()
            ->orderBy('name')
            ->paginate(10)
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'roles' => $user->getRoleNames()->all(),
                'is_superadmin' => $user->hasRole('superadmin'),
            ]);

        return Inertia::render('admin/users', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Create a new user and assign a role.
     */
    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->except('role'));

        // Seul le superadmin attribue les rôles : un administrateur crée
        // un compte qui reçoit le rôle gestionnaire par défaut.
        $role = $this->canAssignRoles($request)
            ? $request->string('role')->toString()
            : 'gestionnaire';

        $user->syncRoles([$role]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Utilisateur créé avec succès.']);

        return to_route('users.index');
    }

    /**
     * Update a user, optionally resetting their password and role.
     */
    public function update(UserRequest $request, User $user): RedirectResponse
    {
        // Un compte superadmin ne peut être modifié que par lui-même
        // (nom, e-mail, mot de passe) — jamais par un autre utilisateur.
        if ($user->hasRole('superadmin') && ! $user->is($request->user())) {
            abort(403);
        }

        $user->update($request->safe()->except(['role', 'password']));

        if ($request->filled('password')) {
            $user->update(['password' => $request->string('password')->toString()]);
        }

        // Le rôle d'un compte superadmin ne peut jamais être modifié :
        // pas de rétrogradation possible, même par lui-même.
        if ($this->canAssignRoles($request) && ! $user->hasRole('superadmin')) {
            $user->syncRoles([$request->string('role')->toString()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Utilisateur mis à jour avec succès.']);

        return to_route('users.index');
    }

    /**
     * Delete a user, unless it is a superadmin or the authenticated user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Personne ne peut supprimer un compte superadmin.
        if ($user->hasRole('superadmin')) {
            abort(403);
        }

        if ($user->is($request->user())) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Vous ne pouvez pas supprimer votre propre compte.']);

            return back();
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Utilisateur supprimé avec succès.']);

        return to_route('users.index');
    }

    /**
     * Whether the authenticated user is allowed to assign roles.
     */
    private function canAssignRoles(Request $request): bool
    {
        return $request->user() instanceof User && $request->user()->hasRole('superadmin');
    }
}
