<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Role;
use Pterodactyl\Models\User;

class RoleController extends Controller
{
    public const PERMISSIONS = [
        'Settings' => ['settings.view', 'settings.manage'],
        'Application API' => ['api.view', 'api.manage'],
        'Mail Center' => ['mail.view', 'mail.manage'],
        'Update Center' => ['updates.view', 'updates.manage'],
        'Databases' => ['databases.view', 'databases.manage'],
        'Locations' => ['locations.view', 'locations.manage'],
        'Nodes' => ['nodes.view', 'nodes.manage'],
        'Servers' => ['servers.view', 'servers.manage'],
        'Users' => ['users.view', 'users.manage'],
        'Mounts' => ['mounts.view', 'mounts.manage'],
        'Nests & Eggs' => ['nests.view', 'nests.manage'],
        'Roles & Permissions' => ['roles.view', 'roles.manage'],
    ];

    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->withCount('users')->orderBy('name')->get(),
            'permissions' => self::PERMISSIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRole($request);
        Role::query()->create($data);
        $this->alert->success('Rollen er oprettet.')->flash();

        return redirect()->route('admin.roles');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $role->update($this->validateRole($request, $role));
        $this->alert->success('Rollen er opdateret.')->flash();

        return redirect()->route('admin.roles');
    }

    public function delete(Role $role): RedirectResponse
    {
        $role->delete();
        $this->alert->success('Rollen er slettet.')->flash();

        return redirect()->route('admin.roles');
    }

    public function assign(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user) && !$request->user()->root_admin) {
            abort(403, 'Du kan ikke ændre dine egne roller.');
        }

        $ids = $request->input('roles', []);
        $ids = is_array($ids) ? array_map('intval', $ids) : [];
        $user->roles()->sync(Role::query()->whereIn('id', $ids)->pluck('id')->all());
        $this->alert->success('Brugerens roller er opdateret.')->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        $all = collect(self::PERMISSIONS)->flatten()->all();
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'permissions' => 'nullable|array',
        ]);

        $permissions = array_values(array_intersect($request->input('permissions', []), $all));
        $slug = Str::slug($validated['name']);
        $base = $slug ?: 'role';
        $i = 2;
        while (Role::query()->where('slug', $slug)->when($role, fn ($q) => $q->whereKeyNot($role->id))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return [
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'],
            'permissions' => $permissions,
        ];
    }
}
