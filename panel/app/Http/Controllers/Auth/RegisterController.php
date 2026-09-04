<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Rules\Username;
use Pterodactyl\Services\Users\UserCreationService;

class RegisterController extends AbstractLoginController
{
    public function __construct(private UserCreationService $userCreationService)
    {
        parent::__construct();
    }

    /**
     * Register a new Nodexa account and sign the user in immediately.
     */
    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'username' => mb_strtolower(trim((string) $request->input('username'))),
            'name_first' => trim((string) $request->input('name_first')),
            'name_last' => trim((string) $request->input('name_last')),
        ]);

        $data = $request->validate([
            'name_first' => ['required', 'string', 'between:1,191'],
            'name_last' => ['required', 'string', 'between:1,191'],
            'username' => ['required', 'string', 'between:3,191', 'unique:users,username', new Username()],
            'email' => ['required', 'email:strict', 'between:1,191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:191', 'confirmed'],
        ], [
            'username.unique' => 'Brugernavnet er allerede i brug.',
            'email.unique' => 'Der findes allerede en konto med denne e-mailadresse.',
            'password.confirmed' => 'Adgangskoderne er ikke ens.',
            'password.min' => 'Adgangskoden skal være mindst 8 tegn.',
        ]);

        $user = $this->userCreationService->handle([
            'name_first' => $data['name_first'],
            'name_last' => $data['name_last'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'root_admin' => false,
        ]);

        return $this->sendLoginResponse($user, $request);
    }
}
