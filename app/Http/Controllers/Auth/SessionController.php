<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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
            'password' => 'required',
        ]);

        if (Auth::validate($data)) {
            $user = User::where('email', $data['email'])->first();

            if (! $user->is_active) {
                return redirect()->back()->withErrors(['email' => 'Sua conta está inativa. Entre em contato com o administrador.']);
            }

            Auth::login($user);
            session()->flash('status', 'Login realizado com sucesso! Bem-vindo(a) de volta.');

            return to_route('home');
        }

        return redirect()->back()->withErrors(['email' => 'Credenciais inválidas']);
    }

    public function destroy()
    {
        auth()->logout();

        session()->flash('status', 'Você saiu da sua conta com sucesso.');

        return to_route('login');
    }
}
