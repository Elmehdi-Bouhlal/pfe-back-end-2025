<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function index()
    {
        return response()->json(['success' => true , 'user' => Auth::user() ],200);
    }

    public function logout()
    {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return response()->json(['success' => true], 200);
    }

    public function login()
    {
        $credentials = request()->only('email', 'password');

        if (!Auth::guard('web')->attempt($credentials)) {
            return response()->json(['success' => false ,'message' => 'Incorrect Creddentiels'], 401);
        }

        request()->session()->regenerate();

        return response()->json(['success' => true , 'message' => 'User has been connected with success', 'user' => Auth::user()]);
    }
}
