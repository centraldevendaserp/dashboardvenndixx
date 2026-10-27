<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Documents\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filtro de ambiente (tpAmb) na tela de Documentos: emissão de teste convive com
 * a real na mesma tabela, e na NFS-e a homologação ainda repete o número da nota.
 *
 * Vale para a listagem, o relatório e o zip — os três leem do mesmo
 * DocumentTypeQuery.
 */
class AmbienteFilterTest extends TestCase
{
    use DatabaseTransactions;

    private const COD_PROD = '9922150327000000000556667772027099900010';
    private const COD_HOMOLOG = '9922150327000000000556667772027099900011';

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([[self::COD_PROD, '1', '4001'], [self::COD_HOMOLOG, '2', '4002']] as [$cod, $amb, $num]) {
            DB::table('nfse_documents')->insert([
                'padrao' => 'ipm', 'cnpj_prestador' => '09617165000181', 'numero' => $num,
                'cod_verificacao' => $cod, 'identidade' => 'IPM:' . $cod,
                'situacao' => 'Autorizada', 'valor' => 10, 'issue_dh' => '2027-06-15',
                'month_year' => '202706', 'environment_type' => $amb,
            ]);
        }
    }

    private function comoAdmin(): void
    {
        $this->actingAs(User::where('email', 'admin@gmail.com')->firstOrFail());
    }

    private function tela(string $ambiente)
    {
        return Livewire::test(Index::class, ['type' => 'nfse'])
            ->set('first_date', '2027-06-01')
            ->set('last_date', '2027-06-30')
            ->set('ambiente', $ambiente);
    }

    public function test_default_e_todos_os_ambientes(): void
    {
        $this->comoAdmin();

        $this->tela('')->assertSee('4001')->assertSee('4002');
    }

    public function test_filtro_producao_esconde_a_homologacao(): void
    {
        $this->comoAdmin();

        $this->tela('1')->assertSee('4001')->assertDontSee('4002');
    }

    public function test_filtro_homologacao_mostra_so_a_de_teste(): void
    {
        $this->comoAdmin();

        $this->tela('2')->assertSee('4002')->assertDontSee('4001');
    }

    /** Linha antiga, sem `environment_type`, conta como produção — é o que ela era. */
    public function test_ambiente_nulo_conta_como_producao(): void
    {
        $cod = '9922150327000000000556667772027099900012';
        DB::table('nfse_documents')->insert([
            'padrao' => 'ipm', 'cnpj_prestador' => '09617165000181', 'numero' => '4003',
            'cod_verificacao' => $cod, 'identidade' => 'IPM:' . $cod,
            'situacao' => 'Autorizada', 'valor' => 10, 'issue_dh' => '2027-06-15',
            'month_year' => '202706', 'environment_type' => null,
        ]);
        $this->comoAdmin();

        $this->tela('1')->assertSee('4003');
        $this->tela('2')->assertDontSee('4003');
    }

    public function test_selo_de_homologacao_aparece_na_listagem(): void
    {
        $this->comoAdmin();

        $this->tela('')->assertSee('Homologação');
    }

    /* ----------------------- relatório e download ----------------------- */

    public function test_relatorio_respeita_o_filtro_de_ambiente(): void
    {
        $this->comoAdmin();
        $base = '/panel/documents/nfse/report?first_date=2027-06-01&last_date=2027-06-30';

        $this->get($base . '&ambiente=1')->assertOk()->assertSee('4001')->assertDontSee('4002');
        $this->get($base . '&ambiente=2')->assertOk()->assertSee('4002')->assertDontSee('4001');
        $this->get($base)->assertOk()->assertSee('4001')->assertSee('4002');
    }

    /** Valor inválido na query string não pode virar filtro — cai em "todos". */
    public function test_ambiente_invalido_no_relatorio_vira_todos(): void
    {
        $this->comoAdmin();

        $this->get('/panel/documents/nfse/report?first_date=2027-06-01&last_date=2027-06-30&ambiente=xyz')
            ->assertOk()->assertSee('4001')->assertSee('4002');
    }

    /**
     * O zip sai da mesma query da tela e do relatório: filtrar produção não pode
     * levar nota de teste junto.
     */
    public function test_query_do_zip_respeita_o_filtro_de_ambiente(): void
    {
        $this->comoAdmin();

        $qtd = fn (string $amb) => (new \App\Support\DocumentTypeQuery(
            type: 'nfse', firstDate: '2027-06-01', lastDate: '2027-06-30', ambiente: $amb
        ))->baseQuery()->count();

        $this->assertSame(1, $qtd('1'));
        $this->assertSame(1, $qtd('2'));
        $this->assertSame(2, $qtd(''));
    }
}
