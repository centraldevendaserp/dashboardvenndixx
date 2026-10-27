<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Documents\Index;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Selo de homologação nas listagens: emissão de teste convive com a real na
 * mesma tabela, e homologação não gasta número — várias emissões repetem o
 * mesmo e parecem nota duplicada.
 *
 * Todas as tabelas usam o MESMO partial (_selo-homolog).
 */
class SeloHomologacaoTest extends TestCase
{
    use DatabaseTransactions;

    private string $cnpj;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::where('email', 'admin@gmail.com')->firstOrFail());
        $this->cnpj = Company::query()->value('cnpj_cpf');
    }

    /**
     * Período fora do dump real. O `event_dh` é TIMESTAMP, cujo teto no MySQL é
     * 2038 — daí 2037, e não 2099.
     */
    private function tela(string $tipo)
    {
        return Livewire::test(Index::class, ['type' => $tipo])
            ->set('company_filter', $this->cnpj)
            ->set('first_date', '2037-01-01')
            ->set('last_date', '2037-12-31');
    }

    /** Documentos: a tabela mais usada era a única sem selo. */
    public function test_nota_de_homologacao_ganha_selo(): void
    {
        foreach ([['1', 940001], ['2', 940002]] as [$amb, $numero]) {
            DB::table('documents')->insert([
                'key' => str_pad((string) $numero, 44, '4', STR_PAD_LEFT),
                'cnpj_cpf' => $this->cnpj, 'ie' => '1', 'model' => 55, 'series' => 1,
                'number' => $numero, 'month_year' => '203703', 'issue_dh' => '2037-03-01',
                'path_xml' => '/x.xml', 'protocol' => '1', 'environment_type' => $amb,
                'status_xml' => 100, 'vNF' => 10,
            ]);
        }

        $this->tela('nfe')->assertSee('940001')->assertSee('940002')->assertSee('doc-selo-homolog', false);
    }

    /** E não aparece quando é tudo produção — selo em nota real seria alarme falso. */
    public function test_nota_de_producao_nao_ganha_selo(): void
    {
        DB::table('documents')->insert([
            'key' => str_pad('940011', 44, '4', STR_PAD_LEFT),
            'cnpj_cpf' => $this->cnpj, 'ie' => '1', 'model' => 55, 'series' => 1,
            'number' => 940011, 'month_year' => '203703', 'issue_dh' => '2037-03-01',
            'path_xml' => '/x.xml', 'protocol' => '1', 'environment_type' => '1',
            'status_xml' => 100, 'vNF' => 10,
        ]);

        $this->tela('nfe')->assertSee('940011')->assertDontSee('doc-selo-homolog', false);
    }

    public function test_cancelamento_de_homologacao_ganha_selo(): void
    {
        DB::table('event_documents')->insert([
            'cnpj' => $this->cnpj, 'model' => 65, 'nfe_key' => str_pad('940021', 44, '4', STR_PAD_LEFT),
            'event_number' => 940021, 'event_desc' => 'Cancelamento', 'event_dh' => '2037-03-01 10:00:00',
            'protocol_number' => '9401', 'environment_type' => '2', 'size' => 1, 'path_xml' => '/x.xml',
            'event_status' => 135, 'event_type' => '110111',
        ]);

        $this->tela('cancelamentos')->assertSee('940021')->assertSee('doc-selo-homolog', false);
    }

    public function test_inutilizacao_de_homologacao_ganha_selo(): void
    {
        DB::table('disable_documents')->insert([
            'environment_type' => '2', 'service' => 'INUTILIZAR', 'uf' => '42', 'year' => '37',
            'cnpj' => $this->cnpj, 'model' => 55, 'series' => 1,
            'number_start' => 940031, 'number_end' => 940031,
            'event_dh' => '2037-03-01 10:00:00', 'event_status' => 102,
            'protocol_number' => '940031999', 'justification' => 'teste',
            'size' => 1, 'path_xml' => '/x.xml',
        ]);

        $this->tela('inutilizacoes')->assertSee('940031999')->assertSee('doc-selo-homolog', false);
    }

    /** Descartes: selo E cor no modelo (o badge que as outras abas já tinham). */
    public function test_descarte_ganha_selo_e_badge_de_modelo(): void
    {
        DB::table('discarded_documents')->insert([
            'cnpj_cpf' => $this->cnpj, 'model' => 65, 'series' => 1, 'number' => 940041,
            'issue_dh' => '2037-03-01', 'month_year' => '203703', 'value' => 10,
            'situacao_erp' => 'I', 'environment_type' => '2', 'identidade' => 'TESTE-SELO-1',
        ]);

        $this->tela('descartes')
            ->assertSee('940041')
            ->assertSee('doc-selo-homolog', false)
            ->assertSee('badge-model', false)
            ->assertSee('mc-nfce', false);
    }

    /** Rejeição sem nota (ledger): mesma regra das demais listagens. */
    public function test_rejeicao_de_homologacao_ganha_selo(): void
    {
        DB::table('fiscal_status')->insert([
            'key' => str_pad('940051', 44, '4', STR_PAD_LEFT), 'model' => 65,
            'cnpj_emit' => preg_replace('/\D/', '', $this->cnpj), 'series' => 1, 'number' => 940051,
            'cstat' => 204, 'category' => 'rejeitada', 'x_motivo' => 'teste',
            'dh_recbto' => '2037-03-01 10:00:00', 'environment_type' => '2', 'source' => 'sit',
        ]);

        $this->tela('nfce')
            ->call('toggleStatus', 'rejeitada')
            ->assertSee('940051')
            ->assertSee('doc-selo-homolog', false);
    }
}
