<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccessInvoice
{
    /** Autoriza a impressão da DANFE: o documento tem de ser de uma empresa do usuário. */
    public function handle(Request $request, Closure $next)
    {
        // ⚠️ route('id'), NUNCA $request->id: o __get do Request lê a query
        // string ANTES do parâmetro de rota, então `?id=` faria este middleware
        // validar um documento e o controller imprimir outro.
        $document = DB::table('documents')->where('id', '=', $request->route('id'))->first();

        if (is_null($document)) {
            abort(404);
        }

        if (!auth('web')->check()) {
            abort(403);
        }

        $user = auth('web')->user();

        if (!$user->isAdmin()) {
            // Comparação estrita: CNPJ/CPF são strings numéricas, e o `==` do
            // PHP 8 as compara como número ('000123...' bateria com '123...').
            if ($user->companies->where('cnpj_cpf', '===', $document->cnpj_cpf)->first() === null) {
                abort(403);
            }
        }

        return $next($request);
    }
}
