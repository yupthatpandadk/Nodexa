<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Users\UserCreationService;

class RegisterController extends Controller
{
    public function __construct(private UserCreationService $creationService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required','string','min:3','max:191','alpha_dash','unique:users,username'],
            'email' => ['required','email','max:191','unique:users,email'],
            'name_first' => ['required','string','max:191'],
            'name_last' => ['required','string','max:191'],
            'password' => ['required','confirmed', Password::min(8)],
        ]);

        $user = $this->creationService->handle(array_merge($data, [
            'root_admin' => false,
            'language' => 'en',
        ]));

        return response()->json(['created' => true, 'username' => $user->username], 201);
    }
}
