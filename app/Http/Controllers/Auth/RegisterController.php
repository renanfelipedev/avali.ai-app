<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Role;
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

        $autoActivate = SystemSetting::getBool('auto_activate_users', false);
        $data['is_active'] = $autoActivate;

        $user = User::create($data);

        // Atribui perfil padrão de Professor (perfil de aluno não deve ser usado)
        $teacherRole = Role::where('slug', UserRole::TEACHER->value)->first();
        if ($teacherRole) {
            $user->roles()->attach($teacherRole);
        }

        if ($autoActivate) {
            session()->flash('status', 'Cadastro realizado com sucesso! Sua conta já está ativa.');
        } else {
            session()->flash('status', 'Seu cadastro foi solicitado, aguarde autorização do administrador');
        }

        return to_route('login');
    }
}
