<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PasswordReset extends Component
{
    public $token;
    public $email;
    public $password;
    public $password_confirmation;

    protected $messages = [
        'email.required' => 'Obrigatório.',
        'email.email' => 'E-mail inválido.',
        'password.required' => 'Obrigatório.',
        'password.min' => 'A senha de deve ter no mínimo 8 caracteres.',
        'password.confirmed' => 'A senha de confirmação não é igual.',
    ];

    public function mount($token)
    {
        $this->token = $token;
    }

    public function render()
    {
        return view('livewire.auth.password-reset', [
            'token' => $this->token
        ])->layout('components.layouts.auth');
    }

    public function submit()
    {
        $credentials = $this->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        // Proteção contra brute force do token: 5 tentativas por e-mail+IP a cada 10min.
        $throttleKey = 'reset:' . sha1(strtolower((string) $this->email) . '|' . request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            session()->flash('message-error', "Muitas tentativas. Tente novamente em {$seconds} segundos.");
            return;
        }

        RateLimiter::hit($throttleKey, 600);

        $credentials['password_confirmation'] = $this->password_confirmation;

        $status = Password::reset($credentials, function ($user, $password) {

            $user->forceFill([
                'password' => bcrypt($password)
            ])->setRememberToken(Str::random(60));

            $user->save();
        });

        if ($status === Password::PASSWORD_RESET) {
            RateLimiter::clear($throttleKey);
            return redirect()->route('auth.login')->with('message-password-reseted', 'Pronto, entre com a nova senha.');
        } else {
            // Mensagem única para qualquer falha, de propósito: distinguir
            // token inválido de e-mail inexistente entrega quem tem conta aqui.
            session()->flash(
                'message-error',
                'Não foi possível criar a nova senha. O link pode ter expirado — solicite um novo.'
            );
        }
    }
}
