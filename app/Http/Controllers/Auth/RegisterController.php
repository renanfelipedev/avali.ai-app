<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\Recaptcha;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function create(Request $request)
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        // Honeypot: se robôs preencherem o campo invisível, simula sucesso e ignora silenciosamente
        if ($request->filled('website_hp')) {
            session()->flash('status', 'Seu cadastro foi solicitado, aguarde autorização do administrador');

            return to_route('login');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'g-recaptcha-response' => ['required', new Recaptcha('register')],
        ], [
            'g-recaptcha-response.required' => 'A validação de segurança (reCAPTCHA) falhou. Por favor, tente novamente.',
        ]);

        unset($data['g-recaptcha-response']);

        $user = User::create($data);

        session()->flash('status', 'Seu cadastro foi solicitado, aguarde autorização do administrador');

        return to_route('login');
    }
}
