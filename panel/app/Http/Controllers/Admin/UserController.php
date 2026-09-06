<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Translation\Translator;
use Pterodactyl\Services\Users\UserUpdateService;
use Pterodactyl\Traits\Helpers\AvailableLanguages;
use Pterodactyl\Services\Users\UserCreationService;
use Pterodactyl\Services\Users\UserDeletionService;
use Pterodactyl\Http\Requests\Admin\UserFormRequest;
use Pterodactyl\Http\Requests\Admin\NewUserFormRequest;
use Pterodactyl\Contracts\Repository\UserRepositoryInterface;

class UserController extends Controller
{
    use AvailableLanguages;

    public function __construct(
        protected AlertsMessageBag $alert,
        protected UserCreationService $creationService,
        protected UserDeletionService $deletionService,
        protected Translator $translator,
        protected UserUpdateService $updateService,
        protected UserRepositoryInterface $repository,
        protected ViewFactory $view,
    ) {
    }

    public function index(Request $request): View
    {
        $users = QueryBuilder::for(
            User::query()->select('users.*')
                ->selectRaw('COUNT(DISTINCT(subusers.id)) as subuser_of_count')
                ->selectRaw('COUNT(DISTINCT(servers.id)) as servers_count')
                ->leftJoin('subusers', 'subusers.user_id', '=', 'users.id')
                ->leftJoin('servers', 'servers.owner_id', '=', 'users.id')
                ->groupBy('users.id')
        )
            ->allowedFilters(['username', 'email', 'uuid'])
            ->defaultSort('-root_admin')
            ->allowedSorts(['id', 'uuid'])
            ->paginate(50);

        return view('admin.users.index', ['users' => $users]);
    }

    public function create(): View
    {
        return view('admin.users.new', [
            'languages' => $this->getAvailableLanguages(true),
        ]);
    }

    public function view(User $user): View
    {
        $roles = DB::table('nodexa_roles')->orderBy('name')->get();
        $assignedRoleIds = DB::table('nodexa_role_user')
            ->where('user_id', $user->id)
            ->pluck('role_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('admin.users.view', [
            'user' => $user,
            'languages' => $this->getAvailableLanguages(true),
            'roles' => $roles,
            'assignedRoleIds' => $assignedRoleIds,
            'canManageRoles' => (bool) request()->user()?->root_admin,
        ]);
    }

    public function delete(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            throw new DisplayException(__('admin/user.exceptions.delete_self'));
        }

        if ($user->root_admin && !$request->user()->root_admin) {
            throw new DisplayException('Kun en root administrator kan slette en anden root administrator.');
        }

        $this->deletionService->handle($user);

        return redirect()->route('admin.users');
    }

    public function store(NewUserFormRequest $request): RedirectResponse
    {
        $data = $request->normalize();

        // Role based admins must never be able to promote a new account to root.
        if (!$request->user()->root_admin) {
            $data['root_admin'] = false;
        }

        $user = $this->creationService->handle($data);
        $this->alert->success($this->translator->get('admin/user.notices.account_created'))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    public function update(UserFormRequest $request, User $user): RedirectResponse
    {
        $data = $request->normalize();

        // Prevent privilege escalation from a role based administrator.
        if (!$request->user()->root_admin) {
            unset($data['root_admin']);
        }

        $this->updateService
            ->setUserLevel(User::USER_LEVEL_ADMIN)
            ->handle($user, $data);

        // Root admins can assign Nodexa roles from the existing user editor.
        if ($request->user()->root_admin && $request->has('roles')) {
            $roleIds = collect($request->input('roles', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            $validRoleIds = DB::table('nodexa_roles')
                ->whereIn('id', $roleIds->all())
                ->pluck('id')
                ->map(fn ($id) => (int) $id);

            DB::transaction(function () use ($user, $validRoleIds) {
                DB::table('nodexa_role_user')->where('user_id', $user->id)->delete();

                foreach ($validRoleIds as $roleId) {
                    DB::table('nodexa_role_user')->insert([
                        'role_id' => $roleId,
                        'user_id' => $user->id,
                    ]);
                }
            });
        }

        $this->alert->success(trans('admin/user.notices.account_updated'))->flash();

        return redirect()->route('admin.users.view', $user->id);
    }

    public function json(Request $request): Model|Collection
    {
        $users = QueryBuilder::for(User::query())->allowedFilters(['email'])->paginate(25);

        if ($request->query('user_id')) {
            $user = User::query()->findOrFail($request->input('user_id'));
            $user->md5 = md5(strtolower($user->email));

            return $user;
        }

        return $users->map(function ($item) {
            $item->md5 = md5(strtolower($item->email));

            return $item;
        });
    }
}
