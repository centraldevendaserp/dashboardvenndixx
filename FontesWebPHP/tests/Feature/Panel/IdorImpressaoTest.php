<?php

namespace Tests\Feature\Panel;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * IDOR na impressão da DANFE: o Request::__get() lê a query string ANTES do
 * parâmetro de rota, então validar `$request->id` e imprimir `route('id')` faz
 * o middleware autorizar um documento e o controller renderizar outro — com id
 * auto-incremento, dá para enumerar a base.
 */
class IdorImpressaoTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0:User,1:int,2:int} usuário não-admin, doc dele, doc de outra empresa */
    private function cenario(): array
    {
        $cnpjs = DB::table('documents')->select('cnpj_cpf')->distinct()->limit(2)->pluck('cnpj_cpf');
        $this->assertCount(2, $cnpjs, 'pré-condição: o dump precisa de 2 CNPJs com documento');

        [$meu, $alheio] = [$cnpjs[0], $cnpjs[1]];

        $user = User::factory()->create(['is_admin' => 'N']);
        $empresa = Company::withoutGlobalScopes()->where('cnpj_cpf', $meu)->first();
        $this->assertNotNull($empresa, 'pré-condição: empresa do CNPJ próprio cadastrada');
        DB::table('user_company')->insert(['user_id' => $user->id, 'company_id' => $empresa->id]);

        return [
            $user,
            (int) DB::table('documents')->where('cnpj_cpf', $meu)->value('id'),
            (int) DB::table('documents')->where('cnpj_cpf', $alheio)->value('id'),
        ];
    }

    public function test_nao_imprime_danfe_de_outra_empresa(): void
    {
        [$user, $meuDoc, $docAlheio] = $this->cenario();

        $this->actingAs($user)
            ->get("/panel/docs/{$docAlheio}/print-invoice")
            ->assertForbidden();
    }

    /** O bypass: a query string mandava no middleware, a rota mandava no controller. */
    public function test_query_string_nao_burla_o_middleware(): void
    {
        [$user, $meuDoc, $docAlheio] = $this->cenario();

        $this->actingAs($user)
            ->get("/panel/docs/{$docAlheio}/print-invoice?id={$meuDoc}")
            ->assertForbidden();
    }

    /**
     * O caminho legítimo continua aberto. Exercita o MIDDLEWARE direto, não a
     * rota: o DomPDF escreve na saída e deixaria o teste "risky".
     */
    public function test_dono_passa_pelo_middleware(): void
    {
        [$user, $meuDoc, $docAlheio] = $this->cenario();
        $this->actingAs($user);

        $this->assertTrue($this->middlewarePassa($meuDoc), 'o dono foi barrado do próprio documento');
        $this->assertFalse($this->middlewarePassa($docAlheio), 'passou num documento de outra empresa');
    }

    /** Roda o access.invoice com o id COMO PARÂMETRO DE ROTA e diz se liberou. */
    private function middlewarePassa(int $id): bool
    {
        $request = \Illuminate\Http\Request::create("/panel/docs/{$id}/print-invoice", 'GET');
        // bind() já extrai o {id} do path. Nada de setParameter aqui: ele
        // retorna void, e o resolver devolveria null.
        $request->setRouteResolver(fn () => (new \Illuminate\Routing\Route(
            'GET', 'panel/docs/{id}/print-invoice', []
        ))->bind($request));

        try {
            (new \App\Http\Middleware\AccessInvoice())->handle($request, fn () => 'liberou');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return false;
        }

        return true;
    }
}
