<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

class ForgotPassword extends Component
{
    public $email;

    protected $messages = [
        'email.required' => 'Obrigatório.',
        'email.email' => 'E-mail inválido.',
    ];

    public function render()
    {
        return view('livewire.auth.forgot-password')->layout('components.layouts.auth');
    }

    public function submit()
    {
        $credentials = $this->validate([
            'email' => 'required|email',
        ]);

        // Proteção contra email-bombing: 5 solicitações por e-mail+IP a cada 10min.
        $throttleKey = 'forgot:' . sha1(strtolower((string) $this->email) . '|' . request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            session()->flash('message-warning', "Muitas solicitações. Tente novamente em {$seconds} segundos.");
            return;
        }

        RateLimiter::hit($throttleKey, 600);

        // O envio é SÍNCRONO (QUEUE_CONNECTION=sync e a notificação não é
        // ShouldQueue): SMTP fora do ar estoura aqui dentro em vez de devolver
        // status, e sem o catch o usuário leva erro sem mensagem nenhuma.
        try {
            $status = Password::sendResetLink($credentials);
        } catch (\Throwable $e) {
            // Vai para o log: é problema de configuração do servidor.
            report($e);

            // Mesma resposta do sucesso, de propósito: mensagem diferente aqui
            // viraria oráculo de enumeração de contas.
            $status = Password::INVALID_USER;
        }

        // Anti-enumeração: e-mail inexistente responde igual ao sucesso.
        if (in_array($status, [Password::RESET_LINK_SENT, Password::RESET_THROTTLED, Password::INVALID_USER], true)) {

            $msg = "Se o e-mail existir, enviamos as instruções de redefinição.";

            session()->flash('message-success', $msg);

            $this->dispatch('eventCuteToast', msg: $msg, code: 200);
            $this->reset('email');

            return;
        }

        // Status inesperado do broker (transporte é tratado acima).
        session()->flash('message-warning', "Não foi possível processar a solicitação. Tente novamente em instantes.");
    }
}
