<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Dashboard\CardInfo;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * KPI "Total filtrado" com o status "Inutilizada": ela não está em `documents`
 * (é faixa de numeração), então o KPI fica sem nada a recortar ali — e sem
 * tratamento a interseção vazia é lida como "não recorta", mostrando o total geral.
 */
class DashboardTotalInutilizadaTest extends TestCase
{
    use DatabaseTransactions;

    private string $cnpj;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'admin@gmail.com')->firstOrFail();
        $this->actingAs($this->admin);
        $this->cnpj = Company::query()->value('cnpj_cpf');
    }

    private function widget(array $search)
    {
        return Livewire::test(CardInfo::class, ['user' => $this->admin])
            ->call('eventDocsSearch', $search);
    }

    private function criaInutilizacao(int $numero): void
    {
        DB::table('disable_documents')->insert([
            'environment_type' => '1', 'service' => 'INUTILIZAR', 'uf' => '42',
            'year' => '37', 'cnpj' => $this->cnpj, 'model' => 55, 'series' => 1,
            'number_start' => $numero, 'number_end' => $numero,
            'event_dh' => '2037-05-10 00:00:00', 'event_status' => 102,
            'protocol_number' => '99' . $numero, 'justification' => 'teste',
            'size' => 1, 'path_xml' => '/x.xml',
        ]);
    }

    /** Filtrar só "Inutilizada" traz a QUANTIDADE de inutilizações, não o total de notas. */
    /** Quantas inutilizações o escopo tem AGORA (o banco de dev já traz algumas). */
    private function inutilizacoesNoEscopo(): int
    {
        return DB::table('disable_documents')
            ->whereIn('cnpj', Company::pluck('cnpj_cpf'))
            ->where('event_status', 102)
            ->count();
    }

    public function test_filtro_inutilizada_conta_as_inutilizacoes(): void
    {
        $antes = $this->inutilizacoesNoEscopo();
        $totalGeralDeNotas = DB::table('documents')->count();
        $this->criaInutilizacao(950001);

        // Ancorado no DELTA, não num absoluto: o dump de dev já tem inutilizações
        // e um número fixo aqui quebraria conforme o banco local mudasse.
        $this->widget(['doc_status' => [102]])
            ->assertSet('invoices_count', $antes + 1)
            ->assertNotSet('invoices_count', $totalGeralDeNotas);
    }

    public function test_duas_inutilizacoes_contam_duas(): void
    {
        $antes = $this->inutilizacoesNoEscopo();
        $this->criaInutilizacao(950011);
        $this->criaInutilizacao(950012);

        $this->widget(['doc_status' => [102]])->assertSet('invoices_count', $antes + 2);
    }

    /** Status de NOTA continua contando só notas — a inutilização não entra. */
    public function test_filtro_autorizada_nao_soma_inutilizacoes(): void
    {
        $this->criaInutilizacao(950021);

        $autorizadas = DB::table('documents')
            ->whereIn('cnpj_cpf', Company::pluck('cnpj_cpf'))
            ->where('status_xml', 100)->count();

        $this->widget(['doc_status' => [100]])
            ->assertSet('invoices_count', $autorizadas);
    }

    /**
     * Sem filtro de status, o KPI é só o total de NOTAS: as inutilizações só
     * entram quando o filtro as pede.
     */
    public function test_sem_filtro_de_status_conta_so_as_notas(): void
    {
        $notas = DB::table('documents')->whereIn('cnpj_cpf', Company::pluck('cnpj_cpf'))->count();

        $this->widget([])->assertSet('invoices_count', $notas);
    }
}
