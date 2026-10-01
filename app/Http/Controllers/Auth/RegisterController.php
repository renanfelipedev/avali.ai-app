<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
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
        if (! SystemSetting::getBool('allow_registration', true)) {
            session()->flash('warning', 'Os cadastros de novos usuários estão temporariamente suspensos pelo administrador.');

            return to_route('login');
        }

        return view('auth.register');
    }

    public function store(Request $request)
    {
        if (! SystemSetting::getBool('allow_registration', true)) {
            session()->flash('warning', 'Os cadastros de novos usuários estão temporariamente suspensos pelo administrador.');

            return to_route('login');
        }

        // Honeypot: se robôs preencherem o campo invisível, simula sucesso e ignora silenciosamente
        if ($request->filled('website_hp')) {
            session()->flash('status', 'Seu cadastro foi solicitado, aguarde autorização do administrador');

            return to_route('login');
        }

        $recaptchaEnabled = SystemSetting::getBool('recaptcha_enabled', true);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];

        if ($recaptchaEnabled) {
            $rules['g-recaptcha-response'] = ['required', new Recaptcha('register')];
        }

        $data = $request->validate($rules, [
            'g-recaptcha-response.required' => 'A validação de segurança (reCAPTCHA) falhou. Por favor, tente novamente.',
        ]);

        unset($data['g-recaptcha-response']);

        $user = User::create($data);

        session()->flash('status', 'Seu cadastro foi solicitado, aguarde autorização do administrador');

        return to_route('login');
    }
}
