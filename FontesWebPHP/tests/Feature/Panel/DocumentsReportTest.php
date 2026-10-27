<?php

namespace Tests\Feature\Panel;

use App\Models\Company;
use App\Models\DisableDocument;
use App\Models\Document;
use App\Models\EventDocument;
use App\Models\FiscalStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Relatório imprimível das telas de Documentos por tipo (stateless, query string revalidada). */
class DocumentsReportTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    private function makeDoc55(int $number, int $status, string $cnpj): Document
    {
        return Document::create([
            'cnpj_cpf'         => $cnpj,
            'ie'               => '000',
            'model'            => 55,
            'series'           => 1,
            'number'           => $number,
            'key'              => str_pad('REPT2099' . $number, 44, '0'),
            'month_year'       => '209901',
            'issue_dh'         => '2099-01-05',
            'path_xml'         => '/tmp/test.xml',
            'protocol'         => 'proto-rep' . $number,
            'environment_type' => '1',
            'status_xml'       => (string) $status,
            'vNF'              => 10.0,
        ]);
    }

    public function test_relatorio_nfe_lista_documentos_e_aplica_chips(): void
    {
        $this->actingAs($this->admin());
        $cnpj = Company::query()->value('cnpj_cpf');

        $autorizada = $this->makeDoc55(1, 100, $cnpj);
        $cancelada = $this->makeDoc55(2, 101, $cnpj);

        $this->get(route('panel.documents.report', [
            'type' => 'nfe',
            'company' => $cnpj,
            'first_date' => '2099-01-01',
            'last_date' => '2099-12-31',
            'status' => ['autorizada'],
        ]))
            ->assertOk()
            ->assertSee($autorizada->key)
            ->assertDontSee($cancelada->key);
    }

    public function test_relatorio_tipo_invalido_da_404(): void
    {
        $this->actingAs($this->admin());

        $this->get('/panel/documents/naoexiste/report')->assertNotFound();
    }

    public function test_relatorio_exige_login(): void
    {
        $this->get(route('panel.documents.report', ['type' => 'nfe']))
            ->assertRedirect(route('auth.login'));
    }

    public function test_relatorio_cancelamentos_usa_view_de_eventos(): void
    {
        $this->actingAs($this->admin());
        $cnpj = Company::query()->value('cnpj_cpf');

        EventDocument::create([
            'cnpj'            => $cnpj,
            'model'           => 55,
            'event_dh'        => '2037-02-05 10:00:00',
            'event_status'    => '135',
            'event_number'    => 1,
            'event_desc'      => 'Cancelamento',
            'nfe_key'         => str_pad('REPTEVT1', 44, '0'),
            'protocol_number' => 'protoevt1',
        ]);

        $this->get(route('panel.documents.report', [
            'type' => 'cancelamentos',
            'company' => $cnpj,
            'first_date' => '2037-02-01',
            'last_date' => '2037-02-28',
        ]))
            ->assertOk()
            ->assertSee('Relatório de eventos')
            ->assertSee(str_pad('REPTEVT1', 44, '0'));
    }

    public function test_relatorio_inutilizacoes_usa_view_de_inutilizadas(): void
    {
        $this->actingAs($this->admin());
        $cnpj = Company::query()->value('cnpj_cpf');

        DisableDocument::create([
            'cnpj'            => $cnpj,
            'model'           => 55,
            'event_dh'        => '2037-03-05 10:00:00',
            'event_status'    => '102',
            'number_start'    => 77,
            'number_end'      => 78,
            'protocol_number' => 'protoinut77',
        ]);

        $this->get(route('panel.documents.report', [
            'type' => 'inutilizacoes',
            'company' => $cnpj,
            'first_date' => '2037-03-01',
            'last_date' => '2037-03-31',
        ]))
            ->assertOk()
            ->assertSee('Relatório de inutilizadas')
            ->assertSee('protoinut77');
    }

    public function test_relatorio_caso_misto_inclui_secao_de_inutilizacoes(): void
    {
        $this->actingAs($this->admin());
        $cnpj = Company::query()->value('cnpj_cpf');

        $doc = $this->makeDoc55(3, 100, $cnpj);
        DisableDocument::create([
            'cnpj'            => $cnpj,
            'model'           => 55,
            'event_dh'        => '2037-01-05 10:00:00',
            'event_status'    => '102',
            'number_start'    => 88,
            'number_end'      => 88,
            'protocol_number' => 'protoinut88',
        ]);

        // Janela ampla 2037..2099 (docs em 2099/DATE; inutilizações em 2037/TIMESTAMP).
        $this->get(route('panel.documents.report', [
            'type' => 'nfe',
            'company' => $cnpj,
            'first_date' => '2037-01-01',
            'last_date' => '2099-12-31',
            'status' => ['autorizada', 'inutilizada'],
        ]))
            ->assertOk()
            ->assertSee($doc->key)
            ->assertSee('Inutilizações')
            ->assertSee('protoinut88');
    }

    public function test_relatorio_nao_vaza_empresa_nao_vinculada(): void
    {
        $cnpj = Company::withoutGlobalScopes()->value('cnpj_cpf');
        $doc = null;

        // Cria o dado como admin (escopo total)...
        $this->actingAs($this->admin());
        $doc = $this->makeDoc55(4, 100, $cnpj);

        // ...e consulta como usuário comum SEM vínculo com empresa alguma.
        $this->actingAs(User::factory()->create(['is_admin' => 'N']));

        $this->get(route('panel.documents.report', [
            'type' => 'nfe',
            'company' => $cnpj,
            'first_date' => '2099-01-01',
            'last_date' => '2099-12-31',
        ]))
            ->assertOk()
            ->assertDontSee($doc->key);
    }

    public function test_relatorio_nfse_renderiza_template_proprio(): void
    {
        $this->actingAs($this->admin());
        $cnpj = Company::query()->value('cnpj_cpf');

        \App\Models\NfseDocument::create([
            'padrao'         => 'nacional',
            'cnpj_prestador' => $cnpj,
            'identidade'     => 'REPT-NFSE-1',
            'valor'          => 123.45,
            'issue_dh'       => '2099-01-05',
            'situacao'       => 'Autorizada',
            'numero'         => 4242,
            'chave'          => str_pad('REPTNFSE1', 44, '0'),
        ]);

        $this->get(route('panel.documents.report', [
            'type' => 'nfse',
            'company' => $cnpj,
            'first_date' => '2099-01-01',
            'last_date' => '2099-12-31',
        ]))
            ->assertOk()
            ->assertSee('Relatório de NFS-e')
            ->assertSee('4242')
            ->assertSee('123,45');
    }

    public function test_relatorio_ignora_parametros_em_formato_array(): void
    {
        $this->actingAs($this->admin());

        // Parâmetros forjados como array não podem derrubar a rota (500).
        $this->get('/panel/documents/nfe/report?company[]=x&first_date[]=y&last_date[]=z')
            ->assertOk();
    }

    public function test_relatorio_limita_e_avisa_em_filtros_gigantes(): void
    {
        $this->actingAs($this->admin());

        // NFC-e com TODOS os períodos (dump real: ~130 mil docs, > teto de 15 mil).
        $total = Document::where('model', 65)->count();
        $this->assertGreaterThan(15000, $total, 'pré-condição: o dump precisa ter mais de 15 mil NFC-e');

        $this->get(route('panel.documents.report', ['type' => 'nfce']))
            ->assertOk()
            ->assertSee('Mostrando os primeiros 15.000')
            ->assertSee(number_format($total, 0, ',', '.'));
    }

    public function test_relatorio_overview_agrega_alem_do_teto(): void
    {
        $this->actingAs($this->admin());

        // A Visão geral agrega no banco: a qty de Autorizada deve ser o TOTAL
        // real (ex.: ~130 mil), não o teto de 15 mil linhas listadas.
        $autorizadas = Document::where('model', 65)->where('status_xml', 100)->count();
        $this->assertGreaterThan(15000, $autorizadas);

        $this->get(route('panel.documents.report', ['type' => 'nfce']))
            ->assertOk()
            ->assertSee((string) $autorizadas);
    }

    public function test_relatorio_do_chip_rejeitada_sozinho_renderiza_o_ledger(): void
    {
        // Rejeição sem-nota no ledger (empresa cadastrada — escopo da tela).
        $company = new Company(['cnpj_cpf' => '88776655000144', 'corporate_name' => 'EMPRESA RELATORIO REJ LTDA']);
        $company->save();
        $key = '42' . '2607' . '88776655000144' . '65' . '001' . '987654320' . '1' . '00000042' . '0';
        FiscalStatus::create([
            'key' => $key, 'model' => 65, 'cnpj_emit' => '88776655000144',
            'series' => 1, 'number' => 987654320, 'cstat' => 704,
            'category' => 'rejeitada', 'x_motivo' => 'Rejeicao: relatorio teste',
            'dh_recbto' => now(), 'environment_type' => '2', 'source' => 'pro-lot',
        ]);

        $this->actingAs($this->admin());

        // Regressão do fix 65781e3: o braço 'rejeicoes' renderiza o ledger (era 500).
        $this->get('/panel/documents/nfce/report?status[]=rejeitada')
            ->assertOk()
            ->assertSee('987654320')
            ->assertSee('Rejeicao: relatorio teste');
    }

    public function test_relatorio_combinado_com_rejeitada_nao_quebra(): void
    {
        $this->actingAs($this->admin());

        $this->get('/panel/documents/nfce/report?status[]=autorizada&status[]=rejeitada')
            ->assertOk();
    }
}
