<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();
        return [
            'id'=>$user->id,
            'name'=>$user->name,
            'email'=>$user->email,
            'is_admin'=>(bool)$user->is_admin,
        ];
    }
}
