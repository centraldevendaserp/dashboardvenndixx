<?php

namespace Tests\Feature\Api;

use App\Models\FiscalStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Canal de status pelo banco do ERP: o agente lê a SITUACAO no Firebird e manda
 * JSON para /api/docs/status-erp/upload. O mapeamento é direto, sem heurística,
 * e `source='erp'` vence o canal XML. NF-e rejeitada chega sem cStat.
 */
class ErpStatusUploadTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Storage::fake('local');
    }

    /** Chave sintética válida: cUF+AAMM+CNPJ+mod+serie+nNF+tpEmis+cNF+DV = 44. */
    private function chave(string $mod = '55', string $nnf = '000000901', string $tpEmis = '1'): string
    {
        return '42' . '2607' . '99887766000177' . $mod . '001' . $nnf . $tpEmis . '00000077' . '0';
    }

    public function test_fiscal_status_aceita_cstat_nulo(): void
    {
        $row = FiscalStatus::create([
            'key' => $this->chave(), 'model' => 55, 'cnpj_emit' => '99887766000177',
            'series' => 1, 'number' => 901, 'cstat' => null,
            'category' => 'rejeitada', 'source' => 'erp',
        ]);

        $this->assertNull($row->fresh()->cstat, 'NF-e rejeitada do ERP não tem cStat — a coluna precisa aceitar NULL.');
    }

    private function post_rows(array $rows)
    {
        return $this->postJson('/api/docs/status-erp/upload', ['key' => 'Sistema', 'rows' => $rows]);
    }

    public function test_contrato_403_sem_chave_e_422_sem_rows(): void
    {
        $this->postJson('/api/docs/status-erp/upload', ['rows' => []])->assertStatus(403);
        $this->postJson('/api/docs/status-erp/upload', ['key' => 'Sistema'])->assertStatus(422);
    }

    public function test_situacao_R_grava_rejeitada_com_cstat_e_motivo(): void
    {
        $chave = $this->chave('65', '000000902');

        $this->post_rows([[
            'chave' => $chave, 'situacao' => 'R',
            'emissao' => '2026-07-16 10:00:00', 'cstat' => 613,
            'xmotivo' => 'Rejeicao: Chave de Acesso difere da existente em BD',
        ]])->assertOk()->assertJson(['msg' => '100', 'gravados' => 1, 'ignorados' => 0]);

        $row = FiscalStatus::where('key', $chave)->firstOrFail();
        $this->assertSame('rejeitada', $row->category);
        $this->assertSame(613, (int) $row->cstat);
        $this->assertSame('erp', $row->source);
        $this->assertSame(65, (int) $row->model);                    // derivado da chave
        $this->assertSame('99887766000177', $row->cnpj_emit);        // derivado da chave
        $this->assertSame(902, (int) $row->number);                  // derivado da chave
    }

    public function test_situacao_O_grava_contingencia_sem_cstat(): void
    {
        $chave = $this->chave('55', '000000903', '9'); // tpEmis 9 = contingência offline

        $this->post_rows([['chave' => $chave, 'situacao' => 'O', 'emissao' => '2026-07-16 10:05:00']])
            ->assertOk()->assertJson(['msg' => '100', 'gravados' => 1]);

        $row = FiscalStatus::where('key', $chave)->firstOrFail();
        $this->assertSame(FiscalStatus::CATEGORY_CONTINGENCIA, $row->category);
        $this->assertNull($row->cstat, 'Contingência do ERP não tem cStat.');
    }

    public function test_transicao_R_para_T_atualiza_a_mesma_linha(): void
    {
        $chave = $this->chave('65', '000000904');

        $this->post_rows([['chave' => $chave, 'situacao' => 'R', 'cstat' => 462]])->assertOk();
        $this->post_rows([['chave' => $chave, 'situacao' => 'T']])->assertOk();

        $row = FiscalStatus::where('key', $chave)->firstOrFail();
        $this->assertSame('autorizada', $row->category, 'R→T na mesma chave: a linha sai da aba (categoria autorizada).');
    }

    public function test_linha_invalida_e_pulada_e_contada(): void
    {
        $boa = $this->chave('65', '000000905');

        $this->post_rows([
            ['chave' => 'chave-invalida', 'situacao' => 'R'],
            ['chave' => $this->chave('65', '000000906'), 'situacao' => 'X'], // situação desconhecida
            ['chave' => $boa, 'situacao' => 'R', 'cstat' => 204],
        ])->assertOk()->assertJson(['msg' => '100', 'gravados' => 1, 'ignorados' => 2]);

        $this->assertNotNull(FiscalStatus::where('key', $boa)->first(), 'A linha boa do lote entra mesmo com vizinhas ruins.');
    }

    /** Reaproveita o formato -sit do canal XML (mesmo padrão do StatusUploadTest). */
    private function xmlSit(string $chave, int $cstat): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<retConsSitNFe versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <tpAmb>1</tpAmb><verAplic>TESTE</verAplic><cStat>{$cstat}</cStat>
  <xMotivo>Motivo teste</xMotivo><cUF>42</cUF><chNFe>{$chave}</chNFe>
  <dhRecbto>2026-07-16T12:00:00-03:00</dhRecbto>
</retConsSitNFe>
XML;
    }

    public function test_canal_xml_nao_sobrescreve_linha_do_erp(): void
    {
        $chave = $this->chave('65', '000000907');

        $this->post_rows([['chave' => $chave, 'situacao' => 'R', 'cstat' => 204]])->assertOk();

        // O canal XML tenta dizer que a mesma chave está autorizada (sit 100, mais NOVO):
        $this->post('/api/docs/status/upload', [
            'key' => 'Sistema',
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent(
                $chave . '-sit.xml', $this->xmlSit($chave, 100)
            ),
        ])->assertOk();

        $row = FiscalStatus::where('key', $chave)->firstOrFail();
        $this->assertSame('erp', $row->source, 'Linha do ERP é autoridade — o XML não pode sobrescrevê-la.');
        $this->assertSame('rejeitada', $row->category);
    }

    public function test_erp_sobrescreve_linha_do_canal_xml(): void
    {
        $chave = $this->chave('65', '000000908');

        $this->post('/api/docs/status/upload', [
            'key' => 'Sistema',
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent(
                $chave . '-sit.xml', $this->xmlSit($chave, 613)
            ),
        ])->assertOk();

        $this->post_rows([['chave' => $chave, 'situacao' => 'T']])->assertOk();

        $row = FiscalStatus::where('key', $chave)->firstOrFail();
        $this->assertSame('erp', $row->source);
        $this->assertSame('autorizada', $row->category, 'O ERP vence: a nota foi retransmitida e autorizada.');
    }

    public function test_linha_do_erp_aparece_nos_chips_da_aba(): void
    {
        $rejeitada = $this->chave('65', '000000909');
        $contingencia = $this->chave('55', '000000910', '9');

        $this->post_rows([
            ['chave' => $rejeitada, 'situacao' => 'R'],           // NF-e/NFC-e sem cstat
            ['chave' => $contingencia, 'situacao' => 'O'],
        ])->assertOk();

        $this->assertSame(1, FiscalStatus::where('key', $rejeitada)->rejeitada()->count(),
            'Rejeitada do ERP (cstat NULL) tem de casar com o chip Rejeitada.');
        $this->assertSame(1, FiscalStatus::where('key', $contingencia)->emContingencia()->count(),
            'Contingência do ERP (categoria armazenada) tem de casar com o chip Contingência.');
        $this->assertSame(2, FiscalStatus::whereIn('key', [$rejeitada, $contingencia])->rejeitadaOuContingencia()->count(),
            'O universo da aba é a união exata dos chips — linha do ERP não pode virar órfã.');
    }

    /**
     * Pior caso da exclusividade dos chips: ERP diz "rejeitada" numa chave de
     * contingência com cStat 217. Sem o guard de source, a mesma linha casaria
     * com os dois chips e seria contada duas vezes.
     */
    public function test_linha_erp_rejeitada_nao_dupla_conta_como_contingencia(): void
    {
        $chave = $this->chave('55', '000000913', '9');

        $this->post_rows([['chave' => $chave, 'situacao' => 'R', 'cstat' => 217]])->assertOk();

        $this->assertSame(1, FiscalStatus::where('key', $chave)->rejeitada()->count(),
            'ERP mandou R: a linha é do chip Rejeitada.');
        $this->assertSame(0, FiscalStatus::where('key', $chave)->emContingencia()->count(),
            'A inferência tpEmis+217 NÃO vale para linha do ERP — a categoria é direta.');
        $this->assertSame(1, FiscalStatus::where('key', $chave)->rejeitadaOuContingencia()->count(),
            'União exata: a linha conta UMA vez no universo da aba.');
    }

    public function test_pagina_renderiza_linha_do_erp_sem_cstat(): void
    {
        $chave = $this->chave('65', '000000911');
        $this->post_rows([['chave' => $chave, 'situacao' => 'R', 'xmotivo' => 'Rejeitada no ERP']])->assertOk();

        $admin = \App\Models\User::factory()->create(['is_admin' => 'S']);
        $this->actingAs($admin);

        // A busca isola a linha nova: sem ela, o volume do banco empurra a
        // linha para fora da página 1 e o assertDontSee passaria à toa.
        \Livewire\Livewire::test(\App\Livewire\Panel\FiscalStatus\Index::class)
            ->set('search', $chave)
            ->assertSee('Rejeitada')
            ->assertDontSee('Rejeitada ()');   // badge sem cstat não imprime parênteses vazios
    }
}
