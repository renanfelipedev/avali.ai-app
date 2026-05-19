<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Show the forgot password form.
     */
    public function request()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send reset link email.
     */
    public function email(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'O campo e-mail é obrigatório.',
            'email.email' => 'Insira um e-mail válido.',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withErrors(['email' => 'Não encontramos um usuário com este endereço de e-mail.']);
        }

        // Generate secure token
        $token = Str::random(60);

        // Save token to database
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Dispatch Email
        Mail::to($request->email)->send(new ResetPasswordMail($request->email, $token, $user->name));

        return back()->with('status', 'Enviamos o link de recuperação para o seu e-mail!');
    }

    /**
     * Show the reset password form.
     */
    public function reset(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    /**
     * Handle updating the password.
     */
    public function update(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ], [
            'email.required' => 'O campo e-mail é obrigatório.',
            'email.email' => 'Insira um e-mail válido.',
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve ter pelo menos 6 caracteres.',
            'password.confirmed' => 'A confirmação de senha não confere.',
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (! $record || ! Hash::check($request->token, $record->token)) {
            return back()->withErrors(['email' => 'Este link de recuperação é inválido ou expirou.']);
        }

        // Check if token is older than 60 minutes
        $createdAt = Carbon::parse($record->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return back()->withErrors(['email' => 'Este link de recuperação expirou. Solicite um novo link.']);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withErrors(['email' => 'Não foi possível encontrar um usuário com este e-mail.']);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Clean up token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        session()->flash('status', 'Sua senha foi redefinida com sucesso! Agora você pode fazer login.');

        return to_route('login');
    }
}
