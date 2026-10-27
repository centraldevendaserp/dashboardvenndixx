<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class Login extends Component
{
    public $email;
    public $password;
    public $remember = false;

    protected $messages = [
        'email.required' => 'Obrigatório.',
        'email.email' => 'E-mail inválido.',
        'password.required' => 'Obrigatório.',
    ];

    public function render()
    {
        return view('livewire.auth.login')->layout('components.layouts.auth');
    }

    public function submit()
    {
        $this->resetErrorBag();

        // Validação manual (sem exception) para avisar o front em qualquer
        // caminho de falha: o spinner do "ENTRAR" só desliga quando o login não
        // vai acontecer. No sucesso ele segue rodando até a próxima página.
        $validator = Validator::make(
            ['email' => $this->email, 'password' => $this->password],
            ['email' => 'required|email', 'password' => 'required'],
            $this->messages
        );

        if ($validator->fails()) {
            $this->setErrorBag($validator->getMessageBag());
            $this->dispatch('auth-finished');   // desliga o spinner
            return;
        }

        // Brute force: 5 tentativas por e-mail+IP a cada 60s. A chave é um
        // hash, então não revela se a conta existe.
        $throttleKey = 'login:' . sha1(strtolower((string) $this->email) . '|' . request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            session()->flash('message-warning', "Muitas tentativas de acesso. Tente novamente em {$seconds} segundos.");
            $this->dispatch('auth-finished');   // estourou o limite: desliga o spinner
            return;
        }

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {

            RateLimiter::clear($throttleKey);

            request()->session()->regenerate();

            session()->flash('message-success', "Logado com sucesso.");

            // Sucesso não dispara 'auth-finished': o spinner segue até navegar.
            return redirect()->intended(route('panel.dashboard.index'));
        }

        RateLimiter::hit($throttleKey, 60);

        session()->flash('message-warning', "E-mail ou senha inválido.");
        $this->dispatch('auth-finished');       // credencial inválida: desliga o spinner
    }
}
