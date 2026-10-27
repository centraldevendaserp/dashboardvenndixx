<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Dashboard\Disable;
use App\Livewire\Panel\Dashboard\Event;
use App\Livewire\Panel\Dashboard\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Default do dashboard = TODOS os documentos, paginados de 100 em 100.
 * Recortando o mês corrente sem filtro, documento antigo "sumia" da tela.
 */
class DashboardDefaultTodosTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '66554433000122';
    private const CHAVE = '42250166554433000122550010000004561000000420';

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => 'S']);
    }

    private function criaEmpresa(): void
    {
        if (!DB::table('companies')->where('cnpj_cpf', self::CNPJ)->exists()) {
            DB::table('companies')->insert([
                'cnpj_cpf' => self::CNPJ,
                'corporate_name' => 'EMPRESA TESTE DEFAULT TODOS LTDA',
            ]);
        }
    }

    public function test_tabela_de_notas_sem_filtro_mostra_documento_antigo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->criaEmpresa();

        DB::table('documents')->insert([
            'cnpj_cpf' => self::CNPJ,
            'ie' => '123456789',
            'model' => 55,
            'series' => 1,
            'number' => 456,
            'key' => self::CHAVE,
            'month_year' => '202501',
            'issue_dh' => '2025-01-15',
            'path_xml' => '/docs/teste-default.xml',
            'protocol' => '342250000000456',
            'environment_type' => '1',
            'status_xml' => '100',
            'vNF' => 50,
        ]);

        $keys = Livewire::test(Invoice::class, ['user' => $admin])
            ->instance()->getInvoices(false)->pluck('key');

        $this->assertTrue($keys->contains(self::CHAVE), 'Sem filtro, nota antiga deve aparecer (default = todos).');
    }

    public function test_aba_autorizadas_mostra_so_status_100(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->criaEmpresa();

        DB::table('documents')->insert([
            'cnpj_cpf' => self::CNPJ,
            'ie' => '123456789',
            'model' => 55,
            'series' => 1,
            'number' => 1001,
            'key' => '42250166554433000122550010000009991000000991',
            'month_year' => '202501',
            'issue_dh' => '2025-01-15',
            'path_xml' => '/docs/teste-autorizada.xml',
            'protocol' => '342250000001001',
            'environment_type' => '1',
            'status_xml' => '100',
            'vNF' => 50,
        ]);

        DB::table('documents')->insert([
            'cnpj_cpf' => self::CNPJ,
            'ie' => '123456789',
            'model' => 55,
            'series' => 1,
            'number' => 1002,
            'key' => '42250166554433000122550010000009992000000992',
            'month_year' => '202501',
            'issue_dh' => '2025-01-16',
            'path_xml' => '/docs/teste-cancelada.xml',
            'protocol' => '342250000001002',
            'environment_type' => '1',
            'status_xml' => '101',
            'vNF' => 60,
        ]);

        // escopa pela empresa de teste (o dump tem milhares de autorizadas de
        // outras empresas); a aba AUTORIZADAS filtra status 100 por cima disso.
        $keys = Livewire::test(Invoice::class, ['user' => $admin, 'doc_type' => 'authorized'])
            ->dispatch('eventDocsSearch', [
                'first_date' => null,
                'last_date' => null,
                'doc_number' => null,
                'protocol_number' => null,
                'related_companies' => [self::CNPJ],
                'doc_types' => [],
                'environment_types' => [],
                'doc_status' => [],
                'quick_search' => null,
            ])
            ->instance()->getInvoices(false)
            ->pluck('status_xml')
            ->values();

        $this->assertSame(['100'], $keys->all(), 'A aba AUTORIZADAS deve exibir apenas documentos com status 100.');
    }

    public function test_listagem_vem_do_mais_recente_para_o_mais_antigo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->criaEmpresa();

        $antiga = '42250166554433000122550010000001111000000411';
        $nova = '42260766554433000122550010000002221000000422';

        foreach ([[$antiga, 111, '2025-01-10'], [$nova, 222, '2026-07-08']] as [$key, $num, $dh]) {
            DB::table('documents')->insert([
                'cnpj_cpf' => self::CNPJ,
                'ie' => '123456789',
                'model' => 55,
                'series' => 1,
                'number' => $num,
                'key' => $key,
                'month_year' => substr(str_replace('-', '', $dh), 0, 6),
                'issue_dh' => $dh,
                'path_xml' => '/docs/teste-ordem.xml',
                'protocol' => '34225000000' . $num,
                'environment_type' => '1',
                'status_xml' => '100',
                'vNF' => 10,
            ]);
        }

        $keys = Livewire::test(Invoice::class, ['user' => $admin])
            ->instance()->getInvoices(false)->pluck('key')->values();

        $this->assertTrue(
            $keys->search($nova) < $keys->search($antiga),
            'A nota mais recente deve vir ANTES da mais antiga (ordem decrescente por emissão).'
        );
    }

    public function test_tabela_de_eventos_sem_filtro_mostra_evento_antigo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->criaEmpresa();

        DB::table('event_documents')->insert([
            'environment_type' => '1',
            'cnpj' => self::CNPJ,
            'model' => 55,
            'nfe_key' => self::CHAVE,
            'event_dh' => '2025-01-16',
            'event_type' => 110111,
            'event_number' => 1,
            'event_desc' => 'Cancelamento',
            'protocol_number' => '342250000000999',
            'justification' => 'TESTE',
            'event_status' => 135,
            'correction' => '',
            'size' => 100,
            'path_xml' => '/docs/eventos/teste-default.xml',
        ]);

        $keys = Livewire::test(Event::class, ['user' => $admin])
            ->instance()->getEvents(false)->pluck('nfe_key');

        $this->assertTrue($keys->contains(self::CHAVE), 'Sem filtro, evento antigo deve aparecer (default = todos).');
    }

    public function test_tabela_de_inutilizadas_sem_filtro_mostra_registro_antigo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->criaEmpresa();

        DB::table('disable_documents')->insert([
            'environment_type' => '1',
            'event_dh' => '2025-01-17',
            'service' => 'INUTILIZAR',
            'uf' => 42,
            'year' => 25,
            'cnpj' => self::CNPJ,
            'model' => 55,
            'series' => 1,
            'number_start' => 777,
            'number_end' => 777,
            'justification' => 'TESTE',
            'event_status' => 102,
            'protocol_number' => '342250000000777',
            'size' => 100,
            'path_xml' => '/docs/eventos/teste-default-inut.xml',
        ]);

        $starts = Livewire::test(Disable::class, ['user' => $admin])
            ->instance()->getDisables(false)->pluck('number_start');

        $this->assertTrue($starts->contains(777), 'Sem filtro, inutilização antiga deve aparecer (default = todos).');
    }
}
