<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Support\NodexaPermissions;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $roles = DB::table('nodexa_roles')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(function ($role) {
                $role->permissions = json_decode((string) $role->permissions, true) ?: [];
                $role->user_ids = DB::table('nodexa_role_user')
                    ->where('role_id', $role->id)
                    ->pluck('user_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $role->user_count = count($role->user_ids);

                return $role;
            });

        $users = DB::table('users')
            ->select(['id', 'username', 'email', 'name_first', 'name_last', 'root_admin'])
            ->orderBy('username')
            ->get();

        return view('admin.roles.index', [
            'roles' => $roles,
            'users' => $users,
            'permissions' => NodexaPermissions::PERMISSIONS,
            'canManage' => NodexaPermissions::userHas($request->user(), 'admin.roles.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $data = $this->validateRole($request);
        $slug = $this->uniqueSlug($data['name']);

        $roleId = DB::table('nodexa_roles')->insertGetId([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?: null,
            'color' => $data['color'],
            'permissions' => json_encode($data['permissions']),
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->syncUsers($roleId, $request->input('users', []));

        return redirect()->route('admin.roles')->with('success', 'Rollen blev oprettet.');
    }

    public function update(Request $request, int $role): RedirectResponse
    {
        $this->ensureCanManage($request);

        $existing = DB::table('nodexa_roles')->where('id', $role)->first();
        abort_if(!$existing, 404);

        $data = $this->validateRole($request, $role);

        DB::table('nodexa_roles')->where('id', $role)->update([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name'], $role),
            'description' => $data['description'] ?: null,
            'color' => $data['color'],
            'permissions' => json_encode($data['permissions']),
            'updated_at' => now(),
        ]);

        $this->syncUsers($role, $request->input('users', []));

        return redirect()->route('admin.roles')->with('success', 'Rollen blev opdateret.');
    }

    public function delete(Request $request, int $role): RedirectResponse
    {
        $this->ensureCanManage($request);

        $existing = DB::table('nodexa_roles')->where('id', $role)->first();
        abort_if(!$existing, 404);

        if ((bool) $existing->is_system) {
            return redirect()->route('admin.roles')->withErrors(['role' => 'Standardroller kan ikke slettes. Du kan i stedet redigere dem.']);
        }

        DB::transaction(function () use ($role) {
            DB::table('nodexa_role_user')->where('role_id', $role)->delete();
            DB::table('nodexa_roles')->where('id', $role)->delete();
        });

        return redirect()->route('admin.roles')->with('success', 'Rollen blev slettet.');
    }

    private function validateRole(Request $request, ?int $roleId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
            'users' => ['array'],
            'users.*' => ['integer'],
        ]);

        $duplicate = DB::table('nodexa_roles')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['name'])]);
        if ($roleId !== null) {
            $duplicate->where('id', '!=', $roleId);
        }
        if ($duplicate->exists()) {
            abort(422, 'Der findes allerede en rolle med dette navn.');
        }

        $allowedPermissions = array_keys(NodexaPermissions::PERMISSIONS);
        $validated['permissions'] = collect($validated['permissions'] ?? [])
            ->filter(fn ($permission) => in_array($permission, $allowedPermissions, true))
            ->unique()
            ->values()
            ->all();

        return $validated;
    }

    private function syncUsers(int $roleId, array $userIds): void
    {
        $validUserIds = DB::table('users')
            ->whereIn('id', collect($userIds)->map(fn ($id) => (int) $id)->filter()->unique()->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        DB::transaction(function () use ($roleId, $validUserIds) {
            DB::table('nodexa_role_user')->where('role_id', $roleId)->delete();

            foreach ($validUserIds as $userId) {
                DB::table('nodexa_role_user')->insert([
                    'role_id' => $roleId,
                    'user_id' => $userId,
                ]);
            }
        });
    }

    private function uniqueSlug(string $name, ?int $ignoreRoleId = null): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $counter = 2;

        while (DB::table('nodexa_roles')
            ->where('slug', $slug)
            ->when($ignoreRoleId !== null, fn ($query) => $query->where('id', '!=', $ignoreRoleId))
            ->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless(NodexaPermissions::userHas($request->user(), 'admin.roles.manage'), 403);
    }
}
