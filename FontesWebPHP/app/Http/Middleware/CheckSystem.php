<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CheckSystem
{
    /**
     * Autentica o agente Delphi por token de instalação (Sanctum, habilidade
     * 'agent:upload') ou pela chave legada, enquanto app.legacy_key_enabled.
     * A credencial vem no campo multipart `key`.
     *
     * Em sucesso marca `agent_authenticated` no request e resolve o dono do
     * token — é o que os controllers leem. Falha responde 403 JSON.
     */
    public function handle(Request $request, Closure $next)
    {
        $providedKey = (string) $request->input('key', '');

        // (1) Token por instalação
        $token = str_contains($providedKey, '|') ? PersonalAccessToken::findToken($providedKey) : null;
        if ($token && $token->can('agent:upload') && ! ($token->expires_at && $token->expires_at->isPast())) {
            $token->forceFill(['last_used_at' => now()])->save();
            $request->setUserResolver(fn () => $token->tokenable);
            $request->attributes->set('agent_authenticated', true);

            return $next($request);
        }

        // (2) Chave legada 'Sistema' (período de convivência)
        if (config('app.legacy_key_enabled')) {
            $expectedKey = (string) config('app.system_access_key');
            if ($expectedKey !== '' && hash_equals($expectedKey, $providedKey)) {
                $request->attributes->set('agent_authenticated', true);
                $request->attributes->set('agent_legacy', true);

                return $next($request);
            }
        }

        // A mensagem tem de dizer O QUE FAZER: o agente loga o corpo da
        // resposta, e é só isso que quem instala vê.
        return response()->json([
            'msg' => 'Chave de acesso invalida. Gere a chave do agente no painel '
                . '(ao cadastrar/editar o usuario) e cole o valor no Key= do .ini.',
        ], 403);
    }
}
