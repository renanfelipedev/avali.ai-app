<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => 'required',
            'password' => 'required'
        ]);


        if (Auth::validate($data)) {
            $user = \App\Models\User::where('email', $data['email'])->first();

            if (! $user->is_active) {
                return redirect()->back()->withErrors(['email' => 'Sua conta está inativa. Entre em contato com o administrador.']);
            }

            Auth::login($user);
            return to_route('home');
        }

        return redirect()->back()->withErrors(['email' => 'Credenciais inválidas']);
    }

    public function destroy()
    {
        auth()->logout();

        return to_route('login');
    }
}
