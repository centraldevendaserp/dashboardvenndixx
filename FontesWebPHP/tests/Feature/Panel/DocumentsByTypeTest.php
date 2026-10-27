<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Documents\Index as DocumentsIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Telas de Documentos por tipo: cada tipo resolve e renderiza, tipo inválido dá
 * 404 e o filtro de status funciona.
 */
class DocumentsByTypeTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    public function test_todas_as_telas_por_tipo_renderizam_para_admin(): void
    {
        $this->actingAs($this->admin());

        foreach (array_keys(DocumentsIndex::types()) as $type) {
            Livewire::test(DocumentsIndex::class, ['type' => $type])
                ->assertOk();
        }
    }

    public function test_tipo_invalido_da_404(): void
    {
        $this->actingAs($this->admin());

        $this->get('/panel/documents/tipo-que-nao-existe')->assertNotFound();
    }

    public function test_filtro_de_status_alterna_e_limpa(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(DocumentsIndex::class, ['type' => 'nfce'])
            ->assertSet('statusFilter', [])
            ->call('toggleStatus', 'cancelada')
            ->assertSet('statusFilter', ['cancelada'])
            ->call('toggleStatus', 'autorizada')
            ->assertSet('statusFilter', ['cancelada', 'autorizada'])
            ->call('toggleStatus', 'cancelada')
            ->assertSet('statusFilter', ['autorizada'])
            ->call('clearStatus')
            ->assertSet('statusFilter', []);
    }

    public function test_grupo_cancelada_filtra_notas_canceladas(): void
    {
        $this->actingAs($this->admin());

        // No dump, NF-e (modelo 55) tem notas com status 101 (Cancelada);
        // o grupo 'cancelada' cobre [101, 135].
        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('first_date', '2015-01-01')
            ->set('last_date', '2030-12-31')
            ->call('toggleStatus', 'cancelada')
            ->assertOk()
            ->assertSeeHtml('wire:key="doc-');
    }

    public function test_grupo_rejeitada_catchall_sem_dados_nao_retorna(): void
    {
        $this->actingAs($this->admin());

        // Não há status de rejeição (2xx–7xx) no dump (só 100/101) → catch-all vazio.
        Livewire::test(DocumentsIndex::class, ['type' => 'nfce'])
            ->set('first_date', '2015-01-01')
            ->set('last_date', '2030-12-31')
            ->call('toggleStatus', 'rejeitada')
            ->assertOk()
            ->assertDontSeeHtml('wire:key="doc-');
    }

    public function test_painel_de_documentos_exige_autenticacao(): void
    {
        $this->get('/panel/documents/nfce')->assertRedirect(route('auth.login'));
    }

    /** Admin enxerga várias empresas, então o select aparece com "Todas as empresas". */
    public function test_select_de_empresa_aparece_quando_ha_mais_de_uma(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->assertOk()
            ->assertSeeHtml('doc-period__company')
            ->assertSee('Todas as empresas');
    }

    /**
     * Empresa válida mantém a tela e o valor; CNPJ forjado não derruba nem vaza
     * dados — cai em "todas".
     */
    public function test_filtro_por_empresa_valida_e_ignora_cnpj_forjado(): void
    {
        $this->actingAs($this->admin());

        $cnpj = \App\Models\Document::where('model', 55)->value('cnpj_cpf');
        $this->assertNotNull($cnpj);

        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('first_date', '2015-01-01')
            ->set('last_date', '2030-12-31')
            ->set('company_filter', $cnpj)
            ->assertOk()
            ->assertSet('company_filter', $cnpj)
            ->assertSeeHtml('wire:key="doc-');

        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('first_date', '2015-01-01')
            ->set('last_date', '2030-12-31')
            ->set('company_filter', '99999999999999')
            ->assertOk();
    }

    /** O <input type="date"> entrega Y-m-d, e o componente tem de filtrar nesse formato. */
    public function test_filtro_de_periodo_no_formato_novo_ymd_retorna_linhas(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(DocumentsIndex::class, ['type' => 'nfce'])
            ->set('first_date', '2024-05-01')
            ->set('last_date', '2026-12-31')
            ->assertOk()
            ->assertSeeHtml('wire:key="doc-');
    }

    /**
     * Compat: o parseDate ainda aceita o formato antigo d/m/Y como fallback.
     */
    public function test_filtro_de_periodo_aceita_formato_antigo_dmy(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(DocumentsIndex::class, ['type' => 'nfce'])
            ->set('first_date', '01/05/2024')
            ->set('last_date', '31/12/2026')
            ->assertOk()
            ->assertSeeHtml('wire:key="doc-');
    }

    /**
     * Um intervalo fora da faixa de dados (formato novo) não deve retornar linhas.
     */
    public function test_filtro_de_periodo_fora_da_faixa_nao_retorna_linhas(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(DocumentsIndex::class, ['type' => 'nfce'])
            ->set('first_date', '1990-01-01')
            ->set('last_date', '1990-12-31')
            ->assertOk()
            ->assertDontSeeHtml('wire:key="doc-');
    }

    private function makeDoc55(int $number, int $status, string $cnpj): \App\Models\Document
    {
        return \App\Models\Document::create([
            'cnpj_cpf'         => $cnpj,
            'ie'               => '000',
            'model'            => 55,
            'series'           => 1,
            'number'           => $number,
            'key'              => str_pad('TEST2099' . $number, 44, '0'),
            'month_year'       => '209901',
            'issue_dh'         => '2099-01-05',
            'path_xml'         => '/tmp/test.xml',
            'protocol'         => 'proto' . $number,
            'environment_type' => '1',
            'status_xml'       => (string) $status,
            'vNF'              => 10.0,
        ]);
    }

    private function makeDisable55(string $cnpj): \App\Models\DisableDocument
    {
        // `disable_documents.event_dh` é TIMESTAMP, cujo teto no MySQL é 2038 —
        // por isso o ano aqui é menor que o 2099 usado nas colunas DATE.
        return \App\Models\DisableDocument::create([
            'cnpj'         => $cnpj,
            'model'        => 55,
            'event_dh'     => '2037-01-05 10:00:00',
            'event_status' => '102',
            'number_start' => 1,
            'number_end'   => 1,
        ]);
    }

    public function test_contador_inutilizada_vem_de_disable_documents(): void
    {
        $this->actingAs($this->admin());
        $cnpj = \App\Models\Company::query()->value('cnpj_cpf');

        $this->makeDoc55(1, 100, $cnpj);   // 1 autorizada (issue_dh 2099, coluna DATE)
        $this->makeDisable55($cnpj);        // 1 inutilização (tabela própria, sem status_xml=102)

        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('company_filter', $cnpj)
            // Janela até 2037: cobre o event_dh TIMESTAMP (teto 2038) e o
            // issue_dh DATE, sem encostar em dado real.
            ->set('first_date', '2037-01-01')
            ->set('last_date', '2099-12-31')
            ->assertViewHas('statusCounts', function ($counts) {
                $map = collect($counts)->keyBy('label');
                return ($map['Autorizada']['qty'] ?? null) === 1
                    && ($map['Inutilizada']['qty'] ?? null) === 1;
            });
    }

    public function test_contadores_por_status_documents(): void
    {
        $this->actingAs($this->admin());
        $cnpj = \App\Models\Company::query()->value('cnpj_cpf');
        $this->assertNotNull($cnpj);

        // 3 autorizadas (100) + 2 canceladas (101), num período isolado (2099).
        foreach ([1, 2, 3] as $n) { $this->makeDoc55($n, 100, $cnpj); }
        foreach ([4, 5] as $n) { $this->makeDoc55($n, 101, $cnpj); }

        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('company_filter', $cnpj)
            ->set('first_date', '2099-01-01')
            ->set('last_date', '2099-12-31')
            ->assertOk()
            ->assertViewHas('statusCounts', function ($counts) {
                $map = collect($counts)->keyBy('label');
                return ($map['Autorizada']['qty'] ?? null) === 3
                    && ($map['Cancelada']['qty'] ?? null) === 2
                    && ! $map->has('Denegada'); // zero omitido
            });
    }

    public function test_contadores_ignoram_o_chip_de_status(): void
    {
        $this->actingAs($this->admin());
        $cnpj = \App\Models\Company::query()->value('cnpj_cpf');

        foreach ([1, 2, 3] as $n) { $this->makeDoc55($n, 100, $cnpj); }
        $this->makeDoc55(4, 101, $cnpj);

        // Mesmo marcando "Cancelada", o detalhamento continua completo.
        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('company_filter', $cnpj)
            ->set('first_date', '2099-01-01')
            ->set('last_date', '2099-12-31')
            ->call('toggleStatus', 'cancelada')
            ->assertViewHas('statusCounts', function ($counts) {
                $map = collect($counts)->keyBy('label');
                return ($map['Autorizada']['qty'] ?? null) === 3
                    && ($map['Cancelada']['qty'] ?? null) === 1;
            });
    }

    public function test_contadores_nfse_por_situacao(): void
    {
        $this->actingAs($this->admin());
        $cnpj = \App\Models\Company::query()->value('cnpj_cpf');

        \App\Models\NfseDocument::create([
            'padrao'         => 'nacional',
            'cnpj_prestador' => $cnpj,
            'identidade'     => 'TEST-1',
            'valor'          => 10.0,
            'issue_dh'       => '2099-01-05',
            'situacao'       => 'Autorizada',
            'chave'          => str_pad('TESTNFSE1', 44, '0'),
        ]);

        Livewire::test(DocumentsIndex::class, ['type' => 'nfse'])
            ->set('company_filter', $cnpj)
            ->set('first_date', '2099-01-01')
            ->set('last_date', '2099-12-31')
            ->assertViewHas('statusCounts', function ($counts) {
                $map = collect($counts)->keyBy('label');
                return ($map['Autorizada']['qty'] ?? null) === 1;
            });
    }

    public function test_contador_total_em_tipos_sem_chips(): void
    {
        $this->actingAs($this->admin());
        $cnpj = \App\Models\Company::query()->value('cnpj_cpf');

        // Inutilizações: 1 registro no período isolado. O event_dh é TIMESTAMP
        // (teto 2038), então a janela vai até 2037.
        $this->makeDisable55($cnpj);

        Livewire::test(DocumentsIndex::class, ['type' => 'inutilizacoes'])
            ->set('company_filter', $cnpj)
            ->set('first_date', '2037-01-01')
            ->set('last_date', '2037-12-31')
            ->assertViewHas('statusCounts', function ($counts) {
                $map = collect($counts)->keyBy('label');

                // "Valor" e o PRIMEIRO card; por isso a busca e
                // por rotulo, nao por indice.
                return ($map['Total']['qty'] ?? null) === 1
                    && $counts[0]['label'] === 'Valor';
            });
    }

    public function test_cabecalho_mostra_pilulas_de_contagem(): void
    {
        $this->actingAs($this->admin());
        $cnpj = \App\Models\Company::query()->value('cnpj_cpf');

        $this->makeDoc55(1, 100, $cnpj);

        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('company_filter', $cnpj)
            ->set('first_date', '2099-01-01')
            ->set('last_date', '2099-12-31')
            ->assertOk()
            ->assertSeeHtml('doc-count')
            ->assertSee('Autorizada');
    }

    public function test_contador_rejeitada_catchall_exclui_codigos_conhecidos(): void
    {
        $this->actingAs($this->admin());
        $cnpj = \App\Models\Company::query()->value('cnpj_cpf');

        // 204 é código desconhecido (>= 200, fora de knownCodes()) → cai no catch-all "Rejeitada".
        $this->makeDoc55(1, 204, $cnpj);
        $this->makeDoc55(2, 204, $cnpj);
        // 205 é conhecido (grupo 'denegada', também >= 200) → prova que o catch-all o EXCLUI.
        $this->makeDoc55(3, 205, $cnpj);

        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('company_filter', $cnpj)
            ->set('first_date', '2099-01-01')
            ->set('last_date', '2099-12-31')
            ->assertViewHas('statusCounts', function ($counts) {
                $map = collect($counts)->keyBy('label');
                return ($map['Rejeitada']['qty'] ?? null) === 2
                    && ($map['Denegada']['qty'] ?? null) === 1;
            });
    }

    public function test_contadores_ignoram_o_chip_inutilizada_mesmo_trocando_a_fonte(): void
    {
        $this->actingAs($this->admin());
        $cnpj = \App\Models\Company::query()->value('cnpj_cpf');

        // 2 autorizadas (tabela documents) + 1 inutilização (tabela disable_documents).
        $this->makeDoc55(1, 100, $cnpj);
        $this->makeDoc55(2, 100, $cnpj);
        $this->makeDisable55($cnpj);

        // Marcar só "inutilizada" troca a fonte principal para disable_documents,
        // mas o detalhamento do cabeçalho ignora o chip e continua completo.
        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('company_filter', $cnpj)
            ->set('first_date', '2037-01-01')
            ->set('last_date', '2099-12-31')
            ->call('toggleStatus', 'inutilizada')
            ->assertViewHas('statusCounts', function ($counts) {
                $map = collect($counts)->keyBy('label');
                return ($map['Autorizada']['qty'] ?? null) === 2
                    && ($map['Inutilizada']['qty'] ?? null) === 1;
            });
    }

    public function test_contador_total_aparece_mesmo_zerado(): void
    {
        $this->actingAs($this->admin());
        $cnpj = \App\Models\Company::query()->value('cnpj_cpf');

        // Tipo sem chips e período vazio: o "Total" aparece mesmo com qty=0.
        Livewire::test(DocumentsIndex::class, ['type' => 'inutilizacoes'])
            ->set('company_filter', $cnpj)
            ->set('first_date', '2098-01-01')
            ->set('last_date', '2098-12-31')
            ->assertViewHas('statusCounts', function ($counts) {
                $map = collect($counts)->keyBy('label');

                // Sem dado no periodo: "Total" aparece ZERADO (nao e omitido como
                // os demais rotulos) e o card "Valor" vem na frente, com 0,00.
                return ($map['Total']['qty'] ?? null) === 0
                    && $counts[0]['label'] === 'Valor'
                    && (float) $counts[0]['valor'] === 0.0;
            });
    }

    public function test_contador_nfse_omite_situacoes_zeradas(): void
    {
        $this->actingAs($this->admin());
        $cnpj = \App\Models\Company::query()->value('cnpj_cpf');

        \App\Models\NfseDocument::create([
            'padrao'         => 'nacional',
            'cnpj_prestador' => $cnpj,
            'identidade'     => 'TEST-2',
            'valor'          => 10.0,
            'issue_dh'       => '2099-01-05',
            'situacao'       => 'Autorizada',
            'chave'          => str_pad('TESTNFSE2', 44, '0'),
        ]);

        Livewire::test(DocumentsIndex::class, ['type' => 'nfse'])
            ->set('company_filter', $cnpj)
            ->set('first_date', '2099-01-01')
            ->set('last_date', '2099-12-31')
            ->assertViewHas('statusCounts', function ($counts) {
                $map = collect($counts)->keyBy('label');

                // 2 cards: o "Valor" (sempre presente) + a unica situacao com
                // quantidade. "Cancelada" zerada continua omitida.
                return count($counts) === 2
                    && $counts[0]['label'] === 'Valor'
                    && ($map['Autorizada']['qty'] ?? null) === 1
                    && ! $map->has('Cancelada');
            });
    }

    /** Garante que o XML físico dos helpers exista em storage/app/tmp/test.xml. */
    private function ensureTestXml(): void
    {
        $dir = storage_path('app/tmp');
        if (! \Illuminate\Support\Facades\File::isDirectory($dir)) {
            \Illuminate\Support\Facades\File::makeDirectory($dir, 0755, true, true);
        }
        \Illuminate\Support\Facades\File::put("{$dir}/test.xml", '<xml>teste</xml>');
    }

    public function test_baixar_xml_gera_zip_com_o_filtro_da_tela(): void
    {
        $this->actingAs($this->admin());
        $this->ensureTestXml();
        $cnpj = \App\Models\Company::query()->value('cnpj_cpf');

        // makeDoc55 grava path_xml = '/tmp/test.xml' (existe no disco após ensureTestXml)
        $this->makeDoc55(1, 100, $cnpj);
        $this->makeDoc55(2, 101, $cnpj);

        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('company_filter', $cnpj)
            ->set('first_date', '2099-01-01')
            ->set('last_date', '2099-12-31')
            ->call('downloadXmls')
            ->assertRedirectContains('/panel/documents/downloads/');
    }

    public function test_baixar_xml_sem_documentos_avisa_por_toast(): void
    {
        $this->actingAs($this->admin());
        $cnpj = \App\Models\Company::query()->value('cnpj_cpf');

        // Período isolado sem nenhum documento criado.
        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('company_filter', $cnpj)
            ->set('first_date', '2098-01-01')
            ->set('last_date', '2098-12-31')
            ->call('downloadXmls')
            ->assertDispatched('eventCuteToast')
            ->assertDispatched('zip-pronto');
    }

    public function test_baixar_xml_chip_inutilizada_sozinho_baixa_das_inutilizacoes(): void
    {
        $this->actingAs($this->admin());
        $this->ensureTestXml();
        $cnpj = \App\Models\Company::query()->value('cnpj_cpf');

        // Inutilização COM arquivo físico (event_dh em 2037 — coluna TIMESTAMP).
        \App\Models\DisableDocument::create([
            'cnpj'         => $cnpj,
            'model'        => 55,
            'event_dh'     => '2037-01-05 10:00:00',
            'event_status' => '102',
            'number_start' => 90,
            'number_end'   => 90,
            'path_xml'     => '/tmp/test.xml',
        ]);

        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('company_filter', $cnpj)
            ->set('first_date', '2037-01-01')
            ->set('last_date', '2037-12-31')
            ->call('toggleStatus', 'inutilizada')
            ->call('downloadXmls')
            ->assertRedirectContains('/panel/documents/downloads/');
    }

    public function test_baixar_xml_nao_vaza_empresa_nao_vinculada(): void
    {
        // Usuário comum SEM empresas vinculadas: escopo vazio -> nada para baixar.
        $this->actingAs(\App\Models\User::factory()->create(['is_admin' => 'N']));
        $this->ensureTestXml();

        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('first_date', '2099-01-01')
            ->set('last_date', '2099-12-31')
            ->call('downloadXmls')
            ->assertDispatched('eventCuteToast')
            ->assertNoRedirect();
    }

    public function test_baixar_xml_chips_mistos_inclui_inutilizacoes(): void
    {
        $this->actingAs($this->admin());
        $this->ensureTestXml();
        $cnpj = \App\Models\Company::query()->value('cnpj_cpf');

        // Chips mistos: a principal continua em documents e as inutilizações
        // vão para a tabela secundária, com os XMLs na subpasta Inutilizadas/.
        $this->makeDoc55(7, 100, $cnpj); // autorizada (issue_dh 2099, coluna DATE)

        // Inutilização COM arquivo físico (event_dh em 2037 — coluna TIMESTAMP).
        \App\Models\DisableDocument::create([
            'cnpj'         => $cnpj,
            'model'        => 55,
            'event_dh'     => '2037-01-05 10:00:00',
            'event_status' => '102',
            'number_start' => 91,
            'number_end'   => 91,
            'path_xml'     => '/tmp/test.xml',
        ]);

        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('company_filter', $cnpj)
            ->set('first_date', '2037-01-01')
            ->set('last_date', '2099-12-31')
            ->call('toggleStatus', 'autorizada')
            ->call('toggleStatus', 'inutilizada')
            ->call('downloadXmls')
            ->assertRedirectContains('/panel/documents/downloads/');
    }

    public function test_barra_de_filtros_mostra_botoes_relatorio_e_baixar_xml(): void
    {
        $this->actingAs($this->admin());

        // Botões só ícone, com tooltip (title) dizendo o que são.
        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->assertOk()
            ->assertSeeHtml('doc-actions')
            ->assertSeeHtml('wire:click="downloadXmls"')
            ->assertSeeHtml('title="Relatório"')
            ->assertSeeHtml('title="Baixar XML"')
            ->assertSeeHtml('fa-file-alt')
            ->assertSeeHtml('fa-download')
            ->assertSeeHtml('zip-pronto'); // spinner segura até o aviso do backend
    }

    public function test_link_do_relatorio_carrega_os_filtros_da_tela(): void
    {
        $this->actingAs($this->admin());
        $cnpj = \App\Models\Company::query()->value('cnpj_cpf');

        Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('company_filter', $cnpj)
            ->set('first_date', '2099-01-01')
            ->set('last_date', '2099-12-31')
            ->call('toggleStatus', 'cancelada')
            ->assertSeeHtml('documents/nfe/report')
            ->assertSeeHtml('first_date=2099-01-01')
            ->assertSeeHtml('cancelada');
    }

    public function test_baixar_xml_acima_do_limite_avisa_e_nao_baixa(): void
    {
        $this->actingAs($this->admin());

        // NFC-e com TODOS os períodos: o dump real tem ~130 mil docs (> teto de 15 mil).
        Livewire::test(DocumentsIndex::class, ['type' => 'nfce'])
            ->set('first_date', '')
            ->set('last_date', '')
            ->call('downloadXmls')
            ->assertDispatched('eventCuteToast')
            ->assertDispatched('zip-pronto')
            ->assertNoRedirect();
    }

    public function test_rota_de_download_nega_zip_de_outro_usuario(): void
    {
        $this->actingAs($this->admin());

        $dir = storage_path('app/downloads');
        if (! \Illuminate\Support\Facades\File::isDirectory($dir)) {
            \Illuminate\Support\Facades\File::makeDirectory($dir, 0755, true, true);
        }
        \Illuminate\Support\Facades\File::put("{$dir}/nfe-999999-123.zip", 'zip');

        try {
            $this->get(route('panel.documents.download', ['file' => 'nfe-999999-123.zip']))
                ->assertForbidden();
        } finally {
            \Illuminate\Support\Facades\File::delete("{$dir}/nfe-999999-123.zip");
        }
    }

    public function test_rota_de_download_rejeita_nome_malformado(): void
    {
        $this->actingAs($this->admin());

        // Fora do padrão {type}-{user}-{time}.zip -> 404 (regex da rota/controller).
        $this->get('/panel/documents/downloads/foo.zip')->assertNotFound();
        $this->get('/panel/documents/downloads/..%2F..%2F.env')->assertNotFound();
    }

    public function test_download_via_rota_entrega_o_zip_do_proprio_usuario(): void
    {
        $this->actingAs($this->admin());
        $this->ensureTestXml();
        $cnpj = \App\Models\Company::query()->value('cnpj_cpf');

        $this->makeDoc55(8, 100, $cnpj);

        $component = Livewire::test(DocumentsIndex::class, ['type' => 'nfe'])
            ->set('company_filter', $cnpj)
            ->set('first_date', '2099-01-01')
            ->set('last_date', '2099-12-31')
            ->call('downloadXmls');

        // Extrai a URL do redirect efetuado pelo componente e baixa por ela.
        $url = $component->effects['redirect'] ?? null;
        $this->assertNotNull($url, 'downloadXmls deveria redirecionar para a rota de download');
        $this->assertStringContainsString('/panel/documents/downloads/', $url);

        $response = $this->get($url);
        $response->assertOk()
            ->assertHeader('content-type', 'application/zip');

        // Nome amigável do download: "{Tipo} - {dd-mm-aaaa}.zip", sem o sufixo
        // "-e". O nome INTERNO (o da URL) não muda: é a segurança da rota.
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertMatchesRegularExpression('/NF - \d{2}-\d{2}-\d{4}\.zip/', $disposition);
        $this->assertStringNotContainsString('NF-e', $disposition);
    }
}
