<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Dashboard\CardInfo;
use App\Livewire\Panel\Dashboard\Index;
use App\Livewire\Panel\Dashboard\Invoice;
use App\Livewire\Panel\Dashboard\QuickFilter;
use App\Livewire\Panel\Dashboard\RecentDocuments;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filtro único no topo do dashboard (período, busca, ambiente, empresas, tipos,
 * status, número e protocolo). Duas garantias:
 *  1. submit()/resetSearch() propagam o conjunto COMPLETO de args nos dois eventos;
 *  2. o Resumo e os Documentos recentes obedecem ao ambiente — é o que impede
 *     "Produção" de vazar nota de homologação.
 */
class DashboardTopFilterTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '55443322000111';
    private const CHAVE_PROD = '42260155443322000111550010000001111000000411';
    private const CHAVE_HOMOLOG = '42260155443322000111650010000002221000000422';

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => 'S']);
    }

    private function criaEmpresa(): void
    {
        if (!DB::table('companies')->where('cnpj_cpf', self::CNPJ)->exists()) {
            DB::table('companies')->insert([
                'cnpj_cpf' => self::CNPJ,
                'corporate_name' => 'EMPRESA TESTE TOP FILTER LTDA',
            ]);
        }
    }

    private function criaNota(string $key, int $number, string $env, int $model = 55): void
    {
        DB::table('documents')->insert([
            'cnpj_cpf' => self::CNPJ,
            'ie' => '123456789',
            'model' => $model,
            'series' => 1,
            'number' => $number,
            'key' => $key,
            'month_year' => date('Ym'),
            'issue_dh' => date('Y-m-d'),
            'path_xml' => '/docs/teste-top-filter.xml',
            'protocol' => '3422600000' . $number,
            'environment_type' => $env,
            'status_xml' => '100',
            'vNF' => 100,
        ]);
    }

    /* ------------------------- propagação dos args ------------------------- */

    public function test_submit_propaga_ambiente_e_todos_os_filtros(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // doc_status sai EXPANDIDO em cStat: o select manda o grupo e o
        // QuickFilter despacha os códigos (ver DashboardStatusScope).
        $ok = function ($event, $params) {
            $a = $params[0] ?? [];
            return ($a['environment_types'] ?? null) === ['1']
                && ($a['related_companies'] ?? null) === [self::CNPJ]
                && ($a['doc_types'] ?? null) === ['55', '65']
                && ($a['doc_status'] ?? null) === [100]
                && ($a['doc_number'] ?? null) === '173'
                && ($a['protocol_number'] ?? null) === '999';
        };

        Livewire::test(QuickFilter::class, ['user' => $admin])
            ->set('environment_type', '1')
            ->set('related_companies', [self::CNPJ])
            ->set('doc_types', ['55', '65'])
            ->set('doc_status', ['autorizada'])
            ->set('doc_number', '173')
            ->set('protocol_number', '999')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertDispatched('eventDocsSearch', $ok)
            ->assertDispatched('eventDocsPerPeriodSearch', $ok);
    }

    public function test_ambiente_todos_manda_environment_types_vazio(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // ambiente = '' (Todos) + algum outro filtro para não cair no "tudo vazio"
        Livewire::test(QuickFilter::class, ['user' => $admin])
            ->set('environment_type', '')
            ->set('doc_status', ['100'])
            ->call('submit')
            ->assertDispatched('eventDocsSearch', function ($event, $params) {
                return ($params[0]['environment_types'] ?? 'x') === [];
            });
    }

    public function test_reset_limpa_todos_os_campos(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(QuickFilter::class, ['user' => $admin])
            ->set('environment_type', '2')
            ->set('related_companies', [self::CNPJ])
            ->set('doc_types', ['55'])
            ->set('doc_status', ['101'])
            ->set('doc_number', '5')
            ->set('protocol_number', '6')
            ->set('term', '173')
            ->call('resetSearch')
            ->assertSet('environment_type', '')
            ->assertSet('related_companies', [])
            ->assertSet('doc_types', [])
            ->assertSet('doc_status', [])
            ->assertSet('doc_number', null)
            ->assertSet('protocol_number', null)
            ->assertSet('term', null)
            ->assertDispatched('eventDocsSearch')
            ->assertDispatched('eventDocsPerPeriodSearch');
    }

    public function test_numero_nao_numerico_barra_o_submit(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(QuickFilter::class, ['user' => $admin])
            ->set('doc_number', 'abc')
            ->call('submit')
            ->assertHasErrors(['doc_number'])
            ->assertNotDispatched('eventDocsSearch');
    }

    /* --------- widgets órfãos agora obedecem ao ambiente (crux) --------- */

    public function test_resumo_conta_so_producao_quando_ambiente_producao(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->criaEmpresa();
        $this->criaNota(self::CHAVE_PROD, 111, '1', 55);
        $this->criaNota(self::CHAVE_HOMOLOG, 222, '2', 65);

        $prodCount = DB::table('documents')
            ->where('cnpj_cpf', self::CNPJ)->where('environment_type', '1')->count();

        // escopa pela empresa de teste (o dump tem notas de outras empresas)
        Livewire::test(CardInfo::class, ['user' => $admin])
            ->dispatch('eventDocsSearch', $this->args(['1'], [self::CNPJ]))
            ->assertSet('invoices_count', $prodCount);
    }

    public function test_resumo_ambiente_homologacao_nao_conta_producao(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->criaEmpresa();
        $this->criaNota(self::CHAVE_PROD, 111, '1', 55);
        $this->criaNota(self::CHAVE_HOMOLOG, 222, '2', 65);

        $homologCount = DB::table('documents')
            ->where('cnpj_cpf', self::CNPJ)->where('environment_type', '2')->count();

        // escopa pela empresa de teste (o dump tem notas de outras empresas)
        Livewire::test(CardInfo::class, ['user' => $admin])
            ->dispatch('eventDocsSearch', $this->args(['2'], [self::CNPJ]))
            ->assertSet('invoices_count', $homologCount);
    }

    public function test_recentes_ambiente_producao_esconde_homologacao(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->criaEmpresa();
        $this->criaNota(self::CHAVE_PROD, 111, '1', 55);
        $this->criaNota(self::CHAVE_HOMOLOG, 222, '2', 65);

        $keys = Livewire::test(RecentDocuments::class, ['user' => $admin])
            ->dispatch('eventDocsSearch', $this->args(['1']))
            ->instance()->getRecentDocuments()->pluck('key');

        $this->assertTrue($keys->contains(self::CHAVE_PROD), 'Produção deve aparecer nos recentes.');
        $this->assertFalse($keys->contains(self::CHAVE_HOMOLOG), 'Homologação NÃO pode aparecer com ambiente=Produção.');
    }

    /* -------------------- aba AUTORIZADAS (só status 100) -------------------- */

    private function criaNotaComStatus(string $key, int $number, string $status): void
    {
        DB::table('documents')->insert([
            'cnpj_cpf' => self::CNPJ,
            'ie' => '123456789',
            'model' => 55,
            'series' => 1,
            'number' => $number,
            'key' => $key,
            'month_year' => date('Ym'),
            'issue_dh' => date('Y-m-d'),
            'path_xml' => '/docs/teste-autorizadas.xml',
            'protocol' => '3422600000' . $number,
            'environment_type' => '1',
            'status_xml' => $status,
            'vNF' => 100,
        ]);
    }

    public function test_aba_autorizadas_mostra_so_status_100(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->criaEmpresa();
        $this->criaNotaComStatus(self::CHAVE_PROD, 701, '100');     // autorizada
        $this->criaNotaComStatus(self::CHAVE_HOMOLOG, 702, '101');  // cancelada

        // escopa pela empresa de teste (o dump tem outras notas)
        $args = $this->args([], [self::CNPJ]);

        // aba AUTORIZADAS: só a autorizada
        $auth = Livewire::test(Invoice::class, ['user' => $admin, 'doc_type' => 'authorized'])
            ->dispatch('eventDocsSearch', $args)
            ->instance()->getInvoices(false)->pluck('status_xml');
        $this->assertTrue($auth->contains('100'), 'AUTORIZADAS deve mostrar a nota autorizada.');
        $this->assertFalse($auth->contains('101'), 'AUTORIZADAS NÃO pode mostrar a cancelada.');

        // aba Documento Fiscal: mostra as duas
        $todas = Livewire::test(Invoice::class, ['user' => $admin, 'doc_type' => 'invoice'])
            ->dispatch('eventDocsSearch', $args)
            ->instance()->getInvoices(false)->pluck('status_xml');
        $this->assertTrue($todas->contains('100') && $todas->contains('101'), 'Documento Fiscal mostra as duas.');
    }

    public function test_troca_de_aba_em_runtime_aplica_o_recorte(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->criaEmpresa();
        $this->criaNotaComStatus(self::CHAVE_PROD, 711, '100');
        $this->criaNotaComStatus(self::CHAVE_HOMOLOG, 712, '101');

        $args = $this->args([], [self::CNPJ]);

        $comp = Livewire::test(Invoice::class, ['user' => $admin])
            ->dispatch('eventDocsSearch', $args)
            ->call('eventDocType', 'authorized');

        $keys = $comp->instance()->getInvoices(false)->pluck('status_xml');
        $this->assertFalse($keys->contains('101'), 'Após trocar para AUTORIZADAS, some a cancelada.');
    }

    public function test_index_troca_o_tipo_no_evento(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // as abas disparam eventDocType em broadcast; Index e Invoice recebem
        // direto (a tabela aplica o recorte AUTORIZADAS sozinha).
        Livewire::test(Index::class)
            ->call('eventDocType', 'authorized')
            ->assertSet('doc_type', 'authorized');
    }

    private function args(array $environmentTypes, array $relatedCompanies = []): array
    {
        return [
            'first_date' => null,
            'last_date' => null,
            'doc_number' => null,
            'protocol_number' => null,
            'related_companies' => $relatedCompanies,
            'doc_types' => [],
            'environment_types' => $environmentTypes,
            'doc_status' => [],
            'quick_search' => null,
        ];
    }
}
