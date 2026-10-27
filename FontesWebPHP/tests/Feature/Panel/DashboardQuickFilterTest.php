<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Dashboard\Invoice;
use App\Livewire\Panel\Dashboard\QuickFilter;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Barra de filtro do topo do dashboard: datas à esquerda e um campo de busca à
 * direita (número, protocolo ou chave). Dispara eventDocsSearch e
 * eventDocsPerPeriodSearch, então tabelas e gráficos acompanham.
 */
class DashboardQuickFilterTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '77665544000133';
    private const CHAVE_A = '42260177665544000133550010000001731000000420';
    private const CHAVE_B = '42260177665544000133550010000009991000000421';
    private const PROTOCOLO_A = '342260000685516';
    private const PROTOCOLO_B = '342260000999999';

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => 'S']);
    }

    private function criaNota(string $key, int $number, string $protocol, string $issueDh = '2026-07-01'): void
    {
        // as tabelas do dashboard só mostram CNPJs cadastrados em companies
        if (!DB::table('companies')->where('cnpj_cpf', self::CNPJ)->exists()) {
            DB::table('companies')->insert([
                'cnpj_cpf' => self::CNPJ,
                'corporate_name' => 'EMPRESA TESTE FILTRO RAPIDO LTDA',
            ]);
        }

        DB::table('documents')->insert([
            'cnpj_cpf' => self::CNPJ,
            'ie' => '123456789',
            'model' => 55,
            'series' => 1,
            'number' => $number,
            'key' => $key,
            'month_year' => substr(str_replace('-', '', $issueDh), 0, 6),
            'issue_dh' => $issueDh,
            'path_xml' => '/docs/teste-quickfilter.xml',
            'protocol' => $protocol,
            'environment_type' => '1',
            'status_xml' => '100',
            'vNF' => 100,
        ]);
    }

    /* ------------------------------ QuickFilter ------------------------------ */

    public function test_submit_dispara_os_dois_eventos_com_datas_e_termo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $ok = fn ($event, $params) => ($params[0]['first_date'] ?? null) === '2026-07-01'
            && ($params[0]['last_date'] ?? null) === '2026-07-09'
            && ($params[0]['quick_search'] ?? null) === '173';

        Livewire::test(QuickFilter::class, ['user' => $admin])
            ->set('first_date', '01/07/2026')
            ->set('last_date', '09/07/2026')
            ->set('term', '173')
            ->call('submit')
            ->assertDispatched('eventDocsSearch', $ok)
            ->assertDispatched('eventDocsPerPeriodSearch', $ok);
    }

    public function test_reset_limpa_e_dispara_null(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(QuickFilter::class, ['user' => $admin])
            ->set('term', '173')
            ->call('resetSearch')
            ->assertSet('term', null)
            ->assertDispatched('eventDocsSearch')
            ->assertDispatched('eventDocsPerPeriodSearch');
    }

    public function test_data_invalida_nao_dispara_busca(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(QuickFilter::class, ['user' => $admin])
            ->set('first_date', '99/99/9999')
            ->call('submit')
            ->assertHasErrors(['first_date'])
            ->assertNotDispatched('eventDocsSearch');
    }

    /* ------------------- consumo do quick_search nas tabelas ------------------- */

    private function buscaInvoices(User $admin, string $termo)
    {
        $component = Livewire::test(Invoice::class, ['user' => $admin]);
        $component->dispatch('eventDocsSearch', [
            'first_date' => null,
            'last_date' => null,
            'doc_number' => null,
            'protocol_number' => null,
            'related_companies' => [],
            'doc_types' => [],
            'environment_types' => [],
            'doc_status' => [],
            'quick_search' => $termo,
        ]);

        return $component->instance()->getInvoices(false)->pluck('key');
    }

    public function test_busca_encontra_nota_por_numero_protocolo_e_chave(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->criaNota(self::CHAVE_A, 173, self::PROTOCOLO_A);
        $this->criaNota(self::CHAVE_B, 999, self::PROTOCOLO_B);

        foreach (['173', self::PROTOCOLO_A, self::CHAVE_A] as $termo) {
            $keys = $this->buscaInvoices($admin, $termo);
            $this->assertTrue($keys->contains(self::CHAVE_A), "Termo '{$termo}' deveria achar a nota A.");
            $this->assertFalse($keys->contains(self::CHAVE_B), "Termo '{$termo}' não deveria achar a nota B.");
        }
    }

    public function test_busca_sem_datas_nao_limita_ao_mes_atual(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // nota antiga (fora do mês corrente): o default "mês atual" não pode
        // esconder o resultado de uma busca direta por chave
        $this->criaNota(self::CHAVE_A, 173, self::PROTOCOLO_A, '2025-01-15');

        $keys = $this->buscaInvoices($admin, self::CHAVE_A);
        $this->assertTrue($keys->contains(self::CHAVE_A), 'Busca por chave deve ignorar o período default.');
    }
}
