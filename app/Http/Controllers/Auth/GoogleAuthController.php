<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Log;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToGoogle()
    {
        if (! SystemSetting::getBool('google_login_enabled', true)) {
            return redirect()->route('login')->withErrors(['email' => 'O login com o Google está temporariamente desativado.']);
        }

        return Socialite::driver('google')
            ->scopes([
                'https://www.googleapis.com/auth/classroom.courses.readonly',
                'https://www.googleapis.com/auth/classroom.rosters.readonly',
                'https://www.googleapis.com/auth/classroom.coursework.students',
                'https://www.googleapis.com/auth/drive.readonly',
            ])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent select_account',
            ])
            ->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function handleGoogleCallback()
    {
        if (! SystemSetting::getBool('google_login_enabled', true)) {
            return redirect()->route('login')->withErrors(['email' => 'O login com o Google está temporariamente desativado.']);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            Log::error('Erro na autenticação do Google OAuth: '.$e->getMessage());

            return redirect()->route('login')->withErrors(['email' => 'Falha ao autenticar com o Google. Tente novamente.']);
        }

        // Find or create the user by Google ID or by email
        $user = User::where('google_id', $googleUser->id)
            ->orWhere('email', $googleUser->email)
            ->first();

        if ($user) {
            // Update Google tokens and ID if they weren't set
            $user->update([
                'google_id' => $googleUser->id,
                'google_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken ?? $user->google_refresh_token,
                'google_token_expires_at' => now()->addSeconds($googleUser->expiresIn),
            ]);
        } else {
            // Register a new user
            $user = User::create([
                'name' => $googleUser->name,
                'email' => $googleUser->email,
                'password' => null, // password nullable is supported
                'google_id' => $googleUser->id,
                'google_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken,
                'google_token_expires_at' => now()->addSeconds($googleUser->expiresIn),
                'is_active' => true,
            ]);

            // Assign default teacher role
            $teacherRole = Role::where('slug', UserRole::TEACHER->value)->first();
            if ($teacherRole) {
                $user->roles()->attach($teacherRole);
            }
        }

        if (! $user->is_active) {
            return redirect()->route('login')->withErrors(['email' => 'Sua conta está inativa. Entre em contato com o administrador.']);
        }

        Auth::login($user);

        session()->flash('status', 'Login realizado com sucesso via Google!');

        return redirect()->route('home');
    }
}
