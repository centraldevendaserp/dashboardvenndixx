<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForgotPassword extends Mailable
{
    use Queueable, SerializesModels;

    public $token;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.password.forgot', [
            'url' => $this->urlDeReset()
        ]);
    }

    /**
     * Link ancorado no APP_URL, NÃO em route().
     *
     * ⚠️ route() monta a URL a partir do cabeçalho Host, e o TrustHosts está
     * fora do stack: um Host forjado faria o e-mail legítimo carregar um link
     * que entrega o token do reset a quem o forjou. Sem APP_URL configurado,
     * cai no comportamento antigo em vez de gerar link quebrado.
     */
    private function urlDeReset(): string
    {
        $caminho = route('auth.password.reset', ['token' => $this->token], false);
        $base    = rtrim((string) config('app.url'), '/');

        return $base !== '' ? $base . $caminho : url($caminho);
    }
}
