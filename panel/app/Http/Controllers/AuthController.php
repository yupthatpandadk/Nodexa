<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['email'=>'required|email','password'=>'required|string']);
        $user = User::where('email', strtolower($data['email']))->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message'=>'Invalid email or password.'], 422);
        }
        $user->tokens()->where('name', 'panel')->delete();
        $token = $user->createToken('panel')->plainTextToken;
        return ['token'=>$token,'user'=>['id'=>$user->id,'name'=>$user->name,'email'=>$user->email,'is_admin'=>(bool)$user->is_admin]];
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->noContent();
    }
}
