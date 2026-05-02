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


        if (! Auth::attempt($data)) {
            return redirect()->back()->withErrors(['email' => 'Credenciais inválidas']);
        }

        return to_route('home');
    }

    public function destroy()
    {
        auth()->logout();

        return to_route('login');
    }
}
