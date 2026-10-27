<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Documents\Index as DocumentsIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Validação de /panel/documents/{type}: cada tipo renderiza e os filtros
 * (status, empresa, período) recortam certo. Compara, tipo a tipo, o que a tela
 * devolve com o que está no banco.
 */
class DocumentsPageValidationTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    private function cnpjs()
    {
        return DB::table('companies')->pluck('cnpj_cpf');
    }

    /** Contagem esperada por tipo, direto no banco (mesmo escopo do componente). */
    private function esperado(string $type): int
    {
        return match ($type) {
            'nfe'     => DB::table('documents')->whereIn('cnpj_cpf', $this->cnpjs())->where('model', 55)->count(),
            'nfce'    => DB::table('documents')->whereIn('cnpj_cpf', $this->cnpjs())->where('model', 65)->count(),
            'cte'     => DB::table('documents')->whereIn('cnpj_cpf', $this->cnpjs())->whereIn('model', [57, 67])->count(),
            'mdfe'    => DB::table('documents')->whereIn('cnpj_cpf', $this->cnpjs())->where('model', 58)->count(),
            'entrada' => DB::table('documents')->whereIn('cnpj_cpf', $this->cnpjs())->where('model', 59)->count(),
            'cancelamentos' => DB::table('event_documents')->whereIn('cnpj', $this->cnpjs())->count(),
            'inutilizacoes' => DB::table('disable_documents')->whereIn('cnpj', $this->cnpjs())->count(),
            'nfse'    => DB::table('nfse_documents')->count(),
        };
    }

    public function test_cada_tipo_renderiza_e_bate_com_o_banco(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        foreach (['nfe', 'nfce', 'cte', 'mdfe', 'entrada', 'cancelamentos', 'inutilizacoes'] as $type) {
            $esperado = $this->esperado($type);

            // zera o período (alguns tipos abrem no mês) para comparar all-time
            $component = Livewire::test(DocumentsIndex::class, ['type' => $type])
                ->set('first_date', null)->set('last_date', null)
                ->assertOk();

            $total = $component->viewData('rows')->total();

            $this->assertSame(
                $esperado,
                $total,
                "Tipo '{$type}': a tela mostrou {$total}, o banco tem {$esperado}."
            );
        }
    }

    public function test_inutilizacoes_mostra_os_registros(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $esperado = $this->esperado('inutilizacoes');
        $this->assertGreaterThan(0, $esperado, 'Pré-condição: o dump precisa ter inutilizações.');

        // Período zerado de propósito: aqui se mede a query contra a tabela
        // inteira; o recorte de data tem teste próprio.
        $total = Livewire::test(DocumentsIndex::class, ['type' => 'inutilizacoes'])
            ->set('first_date', null)
            ->set('last_date', null)
            ->assertOk()
            ->viewData('rows')->total();

        $this->assertSame($esperado, $total, "Inutilizações: tela {$total} x banco {$esperado}.");
    }

    public function test_filtro_de_status_cancelada_recorta(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // Cancelada = códigos 101 e 135 na tabela documents (NF-e)
        $esperado = DB::table('documents')
            ->whereIn('cnpj_cpf', $this->cnpjs())
            ->where('model', 55)
            ->whereIn('status_xml', [101, 135])
            ->count();

        $total = Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('first_date', null)->set('last_date', null)
            ->call('toggleStatus', 'cancelada')
            ->viewData('rows')->total();

        $this->assertSame($esperado, $total, "Filtro 'Cancelada' em NF-e: tela {$total} x banco {$esperado}.");
    }

    public function test_chip_inutilizada_no_modelo_mostra_as_inutilizacoes(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // NFC-e (modelo 65): marcar SÓ "Inutilizada" troca a fonte para as
        // inutilizações do modelo 65 (disable_documents).
        $esperadoNfce = DB::table('disable_documents')
            ->whereIn('cnpj', $this->cnpjs())
            ->where('model', 65)
            ->count();
        $this->assertGreaterThan(0, $esperadoNfce, 'Pré-condição: precisa haver inutilização de NFC-e.');

        $component = Livewire::test(DocumentsIndex::class, ['type' => 'nfce'])
            ->set('first_date', null)->set('last_date', null)
            ->call('toggleStatus', 'inutilizada');

        $this->assertSame('disables', $component->instance()->effectiveSource());
        $this->assertSame($esperadoNfce, $component->viewData('rows')->total(),
            'NFC-e + Inutilizada deve mostrar as inutilizações do modelo 65.');

        // NF-e (modelo 55): não há inutilização desse modelo → vazio.
        $esperadoNfe = DB::table('disable_documents')
            ->whereIn('cnpj', $this->cnpjs())
            ->where('model', 55)
            ->count();

        $totalNfe = Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('first_date', null)->set('last_date', null)
            ->call('toggleStatus', 'inutilizada')
            ->viewData('rows')->total();

        $this->assertSame($esperadoNfe, $totalNfe);
    }

    public function test_periodo_padrao_por_tipo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $inicioMes = \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d');
        $hoje = \Carbon\Carbon::now()->format('Y-m-d');

        // Todas as abas abrem no mês corrente: sem isso, a mesma tela responde
        // a perguntas de períodos diferentes conforme a aba.
        foreach (array_keys(DocumentsIndex::types()) as $type) {
            Livewire::test(DocumentsIndex::class, ['type' => $type])
                ->assertSet('first_date', $inicioMes)
                ->assertSet('last_date', $hoje);
        }
    }

    public function test_inutilizada_com_outro_status_mostra_as_duas_tabelas(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // NFC-e: "Cancelada" (notas) + "Inutilizada" (tabela à parte) juntas.
        $cancelNfce = DB::table('documents')->whereIn('cnpj_cpf', $this->cnpjs())
            ->where('model', 65)->whereIn('status_xml', [101, 135])->count();
        $inutNfce = DB::table('disable_documents')->whereIn('cnpj', $this->cnpjs())
            ->where('model', 65)->count();
        $this->assertGreaterThan(0, $inutNfce, 'Pré-condição: inutilização de NFC-e.');

        $c = Livewire::test(DocumentsIndex::class, ['type' => 'nfce'])
            ->set('first_date', null)->set('last_date', null)
            ->call('toggleStatus', 'cancelada')
            ->call('toggleStatus', 'inutilizada');

        // Principal volta a ser 'documents' (as notas canceladas)...
        $this->assertSame('documents', $c->instance()->effectiveSource());
        $this->assertSame($cancelNfce, $c->viewData('rows')->total());

        // ...e as inutilizações aparecem na tabela SECUNDÁRIA.
        $extra = $c->instance()->extraInutilizacoes();
        $this->assertNotNull($extra);
        $this->assertSame($inutNfce, $extra->count());
    }

    public function test_filtro_de_empresa_recorta(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $cnpj = DB::table('disable_documents')->value('cnpj');
        $esperado = DB::table('disable_documents')->where('cnpj', $cnpj)->count();

        $total = Livewire::test(DocumentsIndex::class, ['type' => 'inutilizacoes'])
            ->set('company_filter', $cnpj)
            ->set('first_date', null)   // mede o filtro de EMPRESA, não o de data
            ->set('last_date', null)
            ->viewData('rows')->total();

        $this->assertSame($esperado, $total, "Filtro por empresa em inutilizações: tela {$total} x banco {$esperado}.");
    }
}
