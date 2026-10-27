<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Http\Request;

/**
 * Amarra o que o agente GRAVA às empresas vinculadas ao dono da credencial.
 * Aplicar em toda rota de ingestão: sem isto, um token vale para qualquer CNPJ
 * e a gravação (delete-then-insert) sobrescreve documento de outro cliente.
 *
 * ⚠️ Duas isenções deliberadas: a chave legada (não tem dono a quem amarrar) e
 * o token de admin (admin enxerga todas as empresas no painel).
 */
final class AgentScope
{
    /**
     * CNPJs que esta requisição pode gravar. `null` = sem restrição (chave
     * legada ou token de admin).
     *
     * @return array<int,string>|null
     */
    public static function cnpjsPermitidos(Request $request): ?array
    {
        $dono = $request->user();

        if ($dono === null || $dono->isAdmin()) {
            return null;
        }

        // withoutGlobalScopes: o escopo `linked_user` depende do guard `web` e
        // aqui não há sessão.
        return $dono->companies()->withoutGlobalScopes()
            ->pluck('cnpj_cpf')
            ->map(fn ($c) => preg_replace('/\D/', '', (string) $c))
            ->filter()->unique()->values()->all();
    }

    /** O agente pode gravar neste CNPJ/CPF? */
    public static function permite(Request $request, $documento): bool
    {
        $permitidos = self::cnpjsPermitidos($request);

        if ($permitidos === null) {
            return true;
        }

        return in_array(preg_replace('/\D/', '', (string) $documento), $permitidos, true);
    }

    /**
     * Resposta de recusa. 403 com `msg` != '100' de propósito: o agente não
     * marca o arquivo como enviado e tenta de novo — assim ele sobe sozinho
     * quando o vínculo da empresa for criado.
     */
    public static function recusa($documento)
    {
        return response()->json([
            'msg' => 'A chave deste agente nao esta vinculada ao CNPJ/CPF '
                . preg_replace('/\D/', '', (string) $documento)
                . '. Vincule a empresa ao usuario dono da chave, no painel.',
        ], 403);
    }
}
