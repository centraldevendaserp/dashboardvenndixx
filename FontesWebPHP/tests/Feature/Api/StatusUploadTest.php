<?php

namespace Tests\Feature\Api;

use App\Models\FiscalStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Canal de status fiscal por XML (api/docs/status/upload): recebe os envelopes
 * pro-lot e sit e mantém `fiscal_status` (1 linha por chave, vence o dhRecbto
 * mais novo). Layout reconhecido responde SEMPRE 100, mesmo com 0 registros,
 * para não criar retry eterno.
 */
class StatusUploadTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '99887766000155';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.system_access_key' => 'Sistema']);
    }

    /** Chave sintética de 44 dígitos: cUF AAMM CNPJ mod serie nNF tpEmis cNF cDV. */
    private function chave(string $nnf = '000000123', string $model = '65'): string
    {
        return '42' . '2607' . self::CNPJ . $model . '001' . $nnf . '1' . '00000042' . '0';
    }

    private function protNFe(string $chave, int $cStat, string $dh, string $xMotivo = 'Motivo teste', string $nProt = ''): string
    {
        $prot = $nProt !== '' ? "<nProt>{$nProt}</nProt>" : '';

        return "<protNFe versao=\"4.00\"><infProt><tpAmb>2</tpAmb><chNFe>{$chave}</chNFe>"
            . "<dhRecbto>{$dh}</dhRecbto>{$prot}<cStat>{$cStat}</cStat>"
            . "<xMotivo>{$xMotivo}</xMotivo></infProt></protNFe>";
    }

    /** retEnviNFe = resposta síncrona (NFC-e). O cStat do topo é do LOTE. */
    private function xmlLoteSincrono(string $prots, string $cStatLote = '104'): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<retEnviNFe versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <tpAmb>2</tpAmb><verAplic>TESTE</verAplic><cStat>{$cStatLote}</cStat>
  <xMotivo>Lote processado</xMotivo><cUF>42</cUF>
  <dhRecbto>2026-07-01T10:00:00-03:00</dhRecbto>{$prots}
</retEnviNFe>
XML;
    }

    /** retConsReciNFe = consulta de recibo (NF-e assíncrona), N protNFe. */
    private function xmlLoteAssincrono(string $prots): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<retConsReciNFe versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <tpAmb>2</tpAmb><verAplic>TESTE</verAplic><nRec>420000000000001</nRec>
  <cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>42</cUF>{$prots}
</retConsReciNFe>
XML;
    }

    private function upload(string $filename, string $xml)
    {
        return $this->post('/api/docs/status/upload', [
            'key'  => 'Sistema',
            'file' => UploadedFile::fake()->createWithContent($filename, $xml),
        ]);
    }

    public function test_nega_sem_chave(): void
    {
        $this->post('/api/docs/status/upload')
            ->assertStatus(403)
            ->assertJsonPath('msg', fn ($msg) => $msg !== '100' && filled($msg));
    }

    public function test_sem_arquivo_da_422(): void
    {
        $this->post('/api/docs/status/upload', ['key' => 'Sistema'])
            ->assertStatus(422)
            ->assertJson(['msg' => 'Arquivo XML invalido ou ausente.']);
    }

    public function test_raiz_desconhecida_da_422(): void
    {
        // consSitNFe é o PEDIDO, que o filtro do agente exclui: chegando aqui,
        // o servidor rejeita com 422. A contagem é por delta, porque o banco de
        // dev já tem linhas.
        $antes = FiscalStatus::count();
        $xml = '<?xml version="1.0"?><consSitNFe versao="4.00" '
            . 'xmlns="http://www.portalfiscal.inf.br/nfe"><tpAmb>2</tpAmb></consSitNFe>';

        $this->upload('123-ped-sit.xml', $xml)->assertStatus(422);
        $this->assertSame($antes, FiscalStatus::count());
    }

    public function test_pro_lot_rejeicao_cria_registro_rejeitada(): void
    {
        $chave = $this->chave();
        $xml = $this->xmlLoteSincrono($this->protNFe(
            $chave, 704, '2026-06-29T23:30:00-03:00',
            'Rejeicao: NFC-e com Data-Hora de emissao atrasada'
        ));

        $this->upload('367-pro-lot.xml', $xml)
            ->assertOk()->assertJson(['msg' => '100']);

        $row = FiscalStatus::where('key', $chave)->firstOrFail();
        $this->assertSame(65, (int) $row->model);
        $this->assertSame(self::CNPJ, $row->cnpj_emit);
        $this->assertSame(1, (int) $row->series);
        $this->assertSame(123, (int) $row->number);
        $this->assertSame(704, (int) $row->cstat);
        $this->assertSame('rejeitada', $row->category);
        $this->assertSame('Rejeicao: NFC-e com Data-Hora de emissao atrasada', $row->x_motivo);
        $this->assertNull($row->n_prot);
        $this->assertSame('pro-lot', $row->source);
        $this->assertSame('2', $row->environment_type);
    }

    public function test_pro_lot_com_varias_notas_upserta_todas(): void
    {
        $ch1 = $this->chave('000000201');
        $ch2 = $this->chave('000000202');
        $xml = $this->xmlLoteAssincrono(
            $this->protNFe($ch1, 100, '2026-07-01T10:00:00-03:00', 'Autorizado o uso da NF-e', '342260000000001')
            . $this->protNFe($ch2, 539, '2026-07-01T10:00:01-03:00', 'Rejeicao: duplicidade')
        );

        $this->upload('12-pro-lot.xml', $xml)->assertOk()->assertJson(['msg' => '100']);

        $this->assertSame('autorizada', FiscalStatus::where('key', $ch1)->value('category'));
        $this->assertSame('342260000000001', FiscalStatus::where('key', $ch1)->value('n_prot'));
        $this->assertSame('rejeitada', FiscalStatus::where('key', $ch2)->value('category'));
    }

    public function test_lote_em_processamento_responde_100_sem_registros(): void
    {
        // retEnviNFe cStat 105 sem protNFe: layout reconhecido, nada a gravar.
        // Tem que responder 100 mesmo assim — 422 viraria retry eterno.
        $antes = FiscalStatus::count();
        $this->upload('13-pro-lot.xml', $this->xmlLoteSincrono('', '105'))
            ->assertOk()->assertJson(['msg' => '100']);

        $this->assertSame($antes, FiscalStatus::count());
    }

    public function test_chave_fora_do_escopo_e_pulada(): void
    {
        // Modelo 99 (NFS-e) fora do canal de status: registro pulado, arquivo aceito.
        $chave = $this->chave('000000300', '99');
        $antes = FiscalStatus::count();
        $xml = $this->xmlLoteSincrono($this->protNFe($chave, 100, '2026-07-01T10:00:00-03:00'));

        $this->upload('14-pro-lot.xml', $xml)->assertOk()->assertJson(['msg' => '100']);
        $this->assertSame($antes, FiscalStatus::count());
        $this->assertNull(FiscalStatus::where('key', $chave)->first());
    }

    public function test_precedencia_envelope_antigo_nao_regride(): void
    {
        $chave = $this->chave('000000400');

        // 1) autorização (dh mais novo)
        $this->upload('20-pro-lot.xml', $this->xmlLoteSincrono(
            $this->protNFe($chave, 100, '2026-07-10T10:00:00-03:00', 'Autorizado', '342260000000002')
        ))->assertOk();

        // 2) rejeição ANTIGA da mesma chave (1ª tentativa de emissão) — não regride
        $this->upload('21-pro-lot.xml', $this->xmlLoteSincrono(
            $this->protNFe($chave, 704, '2026-07-01T09:00:00-03:00', 'Rejeicao: antiga')
        ))->assertOk();

        $this->assertSame('autorizada', FiscalStatus::where('key', $chave)->value('category'));

        // 3) envelope MAIS NOVO atualiza
        $this->upload('22-pro-lot.xml', $this->xmlLoteSincrono(
            $this->protNFe($chave, 101, '2026-07-12T10:00:00-03:00', 'Cancelamento homologado')
        ))->assertOk();

        $this->assertSame('cancelada', FiscalStatus::where('key', $chave)->value('category'));
        $this->assertSame(1, FiscalStatus::where('key', $chave)->count());
    }

    public function test_registro_sem_dhrecbto_so_preenche_linha_inexistente(): void
    {
        $chave = $this->chave('000000410');

        // 1) sem linha ainda: registro SEM dhRecbto INSERE (dh_recbto null)
        $this->upload('40-pro-lot.xml', $this->xmlLoteSincrono(
            $this->protNFe($chave, 704, '', 'Rejeicao: sem data')
        ))->assertOk()->assertJson(['msg' => '100']);

        $row = FiscalStatus::where('key', $chave)->firstOrFail();
        $this->assertNull($row->dh_recbto);
        $this->assertSame('rejeitada', $row->category);

        // 2) linha guardada SEM dh + novo COM dh: atualiza
        $this->upload('41-pro-lot.xml', $this->xmlLoteSincrono(
            $this->protNFe($chave, 100, '2026-07-08T10:00:00-03:00', 'Autorizado', '342260000000007')
        ))->assertOk();

        $row->refresh();
        $this->assertSame('autorizada', $row->category);
        $this->assertNotNull($row->dh_recbto);

        // 3) linha guardada COM dh + novo SEM dh: NAO regride
        $this->upload('42-pro-lot.xml', $this->xmlLoteSincrono(
            $this->protNFe($chave, 704, '', 'Rejeicao: sem data de novo')
        ))->assertOk();

        $row->refresh();
        $this->assertSame('autorizada', $row->category);
    }

    public function test_dhrecbto_empatado_atualiza_o_registro(): void
    {
        $chave = $this->chave('000000411');
        $dh = '2026-07-09T10:00:00-03:00';

        $this->upload('43-pro-lot.xml', $this->xmlLoteSincrono(
            $this->protNFe($chave, 100, $dh, 'Autorizado', '342260000000008')
        ))->assertOk();

        // mesmo dhRecbto (empate): regra e >= — atualiza (refresh do xMotivo)
        $this->upload('44-pro-lot.xml', $this->xmlLoteSincrono(
            $this->protNFe($chave, 100, $dh, 'Autorizado o uso da NF-e (reconsulta)', '342260000000008')
        ))->assertOk();

        $row = FiscalStatus::where('key', $chave)->firstOrFail();
        $this->assertSame('Autorizado o uso da NF-e (reconsulta)', $row->x_motivo);
        $this->assertSame(1, FiscalStatus::where('key', $chave)->count());
    }

    /** retConsSitNFe: chave e situação atual vêm do TOPO (confirmado nos arquivos reais). */
    private function xmlSit(string $chave, int $cStat, string $dh, string $xMotivo = 'Motivo teste', string $protEmbutido = ''): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<retConsSitNFe versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <tpAmb>2</tpAmb><verAplic>TESTE</verAplic><cStat>{$cStat}</cStat>
  <xMotivo>{$xMotivo}</xMotivo><cUF>42</cUF>
  <dhRecbto>{$dh}</dhRecbto><chNFe>{$chave}</chNFe>{$protEmbutido}
</retConsSitNFe>
XML;
    }

    public function test_sit_100_cria_autorizada_com_nprot_do_protocolo_embutido(): void
    {
        $chave = $this->chave('000000500');
        $prot = $this->protNFe($chave, 100, '2026-01-09T18:49:05-03:00', 'Autorizado o uso da NF-e', '342260000025266');

        $this->upload("{$chave}-sit.xml", $this->xmlSit(
            $chave, 100, '2026-01-21T14:01:30-03:00', 'Autorizado o uso da NF-e', $prot
        ))->assertOk()->assertJson(['msg' => '100']);

        $row = FiscalStatus::where('key', $chave)->firstOrFail();
        $this->assertSame('autorizada', $row->category);
        $this->assertSame('342260000025266', $row->n_prot);
        $this->assertSame('sit', $row->source);
        // dh do TOPO (consulta), não o da autorização embutida
        $this->assertSame('2026-01-21', $row->dh_recbto->format('Y-m-d'));
    }

    public function test_sit_217_nao_consta_cria_rejeitada(): void
    {
        $chave = $this->chave('000000501');

        $this->upload("{$chave}-sit.xml", $this->xmlSit(
            $chave, 217, '2026-05-19T19:04:32-03:00', 'Rejeicao: NF-e nao consta na base de dados da SEFAZ'
        ))->assertOk()->assertJson(['msg' => '100']);

        $row = FiscalStatus::where('key', $chave)->firstOrFail();
        $this->assertSame('rejeitada', $row->category);
        $this->assertSame(217, (int) $row->cstat);
        $this->assertNull($row->n_prot);
    }

    public function test_sit_sem_chave_responde_100_sem_registro(): void
    {
        $antes = FiscalStatus::count();
        $xml = '<?xml version="1.0"?><retConsSitNFe versao="4.00" '
            . 'xmlns="http://www.portalfiscal.inf.br/nfe"><tpAmb>2</tpAmb>'
            . '<cStat>217</cStat><xMotivo>Rejeicao</xMotivo><cUF>42</cUF></retConsSitNFe>';

        $this->upload('999-sit.xml', $xml)->assertOk()->assertJson(['msg' => '100']);
        $this->assertSame($antes, FiscalStatus::count());
    }

    /** Insere um documento mínimo direto na tabela (como o import faz). */
    private function criaDocumento(string $chave, string $cnpjCpf, int $status = 100): void
    {
        \Illuminate\Support\Facades\DB::table('documents')->insert([
            'cnpj_emit' => self::CNPJ, 'cnpj_cpf' => $cnpjCpf, 'ie' => '123',
            'model' => 65, 'series' => 1, 'number' => 900, 'key' => $chave,
            'month_year' => '202607', 'issue_dh' => '2026-07-01',
            'path_xml' => '/docs/teste.xml', 'protocol' => '342260000000009',
            'environment_type' => '2', 'status_xml' => (string) $status,
            'size' => 1000, 'vNF' => '10.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_ponte_sit_101_cancela_documento_nas_duas_linhas(): void
    {
        $chave = $this->chave('000000600');
        $this->criaDocumento($chave, self::CNPJ);          // saída (emitente)
        $this->criaDocumento($chave, '11222333000144');    // entrada (destinatário)

        $this->upload("{$chave}-sit.xml", $this->xmlSit(
            $chave, 101, '2026-07-05T10:00:00-03:00', 'Cancelamento de NF-e homologado'
        ))->assertOk();

        $status = \Illuminate\Support\Facades\DB::table('documents')
            ->where('key', $chave)->pluck('status_xml');
        $this->assertCount(2, $status);
        $this->assertSame(['101', '101'], $status->map(fn ($s) => (string) $s)->all());
        $this->assertSame('cancelada', FiscalStatus::where('key', $chave)->value('category'));
    }

    public function test_ponte_rejeicao_nao_toca_documento_existente(): void
    {
        $chave = $this->chave('000000601');
        $this->criaDocumento($chave, self::CNPJ);

        $this->upload('30-pro-lot.xml', $this->xmlLoteSincrono(
            $this->protNFe($chave, 704, '2026-07-05T10:00:00-03:00', 'Rejeicao: qualquer')
        ))->assertOk();

        $this->assertSame('100', (string) \Illuminate\Support\Facades\DB::table('documents')
            ->where('key', $chave)->value('status_xml'));
        // A verdade completa fica no ledger:
        $this->assertSame('rejeitada', FiscalStatus::where('key', $chave)->value('category'));
    }

    public function test_ponte_respeita_cancelamento_por_evento(): void
    {
        $chave = $this->chave('000000602');
        $this->criaDocumento($chave, self::CNPJ, 101);

        // Evento de cancelamento homologado já registrado (como o EventsController grava)
        \Illuminate\Support\Facades\DB::table('event_documents')->insert([
            'nfe_key' => $chave, 'event_type' => '110111', 'event_status' => 135,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // sit 100 MAIS NOVO (consultado antes do cancelamento, chegando depois):
        // não pode "descancelar" o documento.
        $this->upload("{$chave}-sit.xml", $this->xmlSit(
            $chave, 100, '2026-07-06T10:00:00-03:00', 'Autorizado o uso da NF-e'
        ))->assertOk();

        $this->assertSame('101', (string) \Illuminate\Support\Facades\DB::table('documents')
            ->where('key', $chave)->value('status_xml'));
    }

    /* --------------------- "fora do prazo" colapsa --------------------- */

    public function test_ponte_sit_150_espelha_documento_como_autorizado(): void
    {
        $chave = $this->chave('000000603');
        $this->criaDocumento($chave, self::CNPJ);

        $this->upload("{$chave}-sit.xml", $this->xmlSit(
            $chave, 150, '2026-07-05T10:00:00-03:00', 'Autorizado o uso da NF-e, autorizacao fora de prazo'
        ))->assertOk();

        $this->assertSame('100', (string) \Illuminate\Support\Facades\DB::table('documents')
            ->where('key', $chave)->value('status_xml'));

        // O ledger preserva a verdade da SEFAZ: cStat REAL 150, categoria autorizada.
        $this->assertSame(150, (int) FiscalStatus::where('key', $chave)->value('cstat'));
        $this->assertSame('autorizada', FiscalStatus::where('key', $chave)->value('category'));
    }

    public function test_ponte_sit_151_espelha_documento_como_cancelado(): void
    {
        $chave = $this->chave('000000604');
        $this->criaDocumento($chave, self::CNPJ);

        $this->upload("{$chave}-sit.xml", $this->xmlSit(
            $chave, 151, '2026-07-05T10:00:00-03:00', 'Cancelamento de NF-e homologado fora de prazo'
        ))->assertOk();

        // Regressão: se o mapa da ponte deixar de conhecer 151, o update é PULADO
        // e uma nota cancelada fica presa em "Autorizada" para o contador.
        $this->assertSame('101', (string) \Illuminate\Support\Facades\DB::table('documents')
            ->where('key', $chave)->value('status_xml'));

        $this->assertSame(151, (int) FiscalStatus::where('key', $chave)->value('cstat'));
        $this->assertSame('cancelada', FiscalStatus::where('key', $chave)->value('category'));
    }

    /** retMDFe = resposta síncrona do MDF-e (raiz real do 0-pro-lot.xml do cliente). */
    private function xmlLoteMdfe(string $chave, int $cStat, string $dh, string $xMotivo = 'Autorizado o uso do MDF-e', string $nProt = '942260000009735'): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<retMDFe xmlns="http://www.portalfiscal.inf.br/mdfe" versao="3.00">
  <tpAmb>2</tpAmb><cUF>42</cUF><verAplic>TESTE</verAplic>
  <cStat>{$cStat}</cStat><xMotivo>{$xMotivo}</xMotivo>
  <protMDFe versao="3.00"><infProt><tpAmb>2</tpAmb><chMDFe>{$chave}</chMDFe>
    <dhRecbto>{$dh}</dhRecbto><nProt>{$nProt}</nProt>
    <cStat>{$cStat}</cStat><xMotivo>{$xMotivo}</xMotivo></infProt></protMDFe>
</retMDFe>
XML;
    }

    /** retConsSitMDFe: topo SEM chave e SEM dhRecbto (como os 21x217 reais). */
    private function xmlSitMdfe(int $cStat, string $xMotivo, string $protEmbutido = ''): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<retConsSitMDFe xmlns="http://www.portalfiscal.inf.br/mdfe" versao="3.00">
  <tpAmb>2</tpAmb><verAplic>TESTE</verAplic><cStat>{$cStat}</cStat>
  <xMotivo>{$xMotivo}</xMotivo><cUF>42</cUF>{$protEmbutido}
</retConsSitMDFe>
XML;
    }

    private function protMdfe(string $chave, int $cStat, string $dh, string $nProt = '942260000009736'): string
    {
        return "<protMDFe versao=\"3.00\"><infProt><tpAmb>2</tpAmb><chMDFe>{$chave}</chMDFe>"
            . "<dhRecbto>{$dh}</dhRecbto><nProt>{$nProt}</nProt><cStat>{$cStat}</cStat>"
            . "<xMotivo>Autorizado o uso do MDF-e</xMotivo></infProt></protMDFe>";
    }

    public function test_ret_mdfe_sincrono_cria_linha_model_58(): void
    {
        $chave = $this->chave('000000700', '58');

        $this->upload('0-pro-lot.xml', $this->xmlLoteMdfe($chave, 100, '2026-05-15T23:57:29-03:00'))
            ->assertOk()->assertJson(['msg' => '100']);

        $row = FiscalStatus::where('key', $chave)->firstOrFail();
        $this->assertSame(58, (int) $row->model);
        $this->assertSame('autorizada', $row->category);
        $this->assertSame('942260000009735', $row->n_prot);
    }

    public function test_sit_mdfe_100_usa_chave_e_dh_do_protocolo_embutido(): void
    {
        $chave = $this->chave('000000701', '58');
        $xml = $this->xmlSitMdfe(100, 'Autorizado o uso do MDF-e',
            $this->protMdfe($chave, 100, '2026-05-15T23:57:29-03:00'));

        // O nome do arquivo NÃO bate com a chave de propósito: a chave tem que
        // vir do protMDFe embutido (prioridade sobre o fallback do filename).
        $this->upload('999-sit.xml', $xml)->assertOk();

        $row = FiscalStatus::where('key', $chave)->firstOrFail();
        $this->assertSame('autorizada', $row->category);
        $this->assertSame('2026-05-15', $row->dh_recbto->format('Y-m-d'));
        $this->assertSame('sit', $row->source);
    }

    public function test_sit_mdfe_217_sem_chave_usa_o_nome_do_arquivo(): void
    {
        $chave = $this->chave('000000702', '58');
        $xml = $this->xmlSitMdfe(217, 'Rejeicao: MDF-e nao consta na base de dados da SEFAZ');

        $this->upload("{$chave}-sit.xml", $xml)->assertOk()->assertJson(['msg' => '100']);

        $row = FiscalStatus::where('key', $chave)->firstOrFail();
        $this->assertSame('rejeitada', $row->category);
        $this->assertSame(217, (int) $row->cstat);
        $this->assertNull($row->dh_recbto); // 217 não traz data em lugar nenhum
    }

    public function test_sit_sem_chave_nem_filename_valido_responde_100_sem_registro(): void
    {
        $antes = FiscalStatus::count();

        $this->upload('consulta-avulsa-sit.xml', $this->xmlSitMdfe(217, 'Rejeicao: nao consta'))
            ->assertOk()->assertJson(['msg' => '100']);

        $this->assertSame($antes, FiscalStatus::count());
    }

    public function test_ret_cte_e_sit_cte_sinteticos(): void
    {
        // CT-e sem amostra real no repo: fixtures pelo schema oficial (ns cte).
        $ch57 = $this->chave('000000703', '57');
        $lote = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<retCTe xmlns="http://www.portalfiscal.inf.br/cte" versao="4.00">
  <tpAmb>2</tpAmb><cUF>42</cUF><verAplic>TESTE</verAplic>
  <cStat>100</cStat><xMotivo>Autorizado o uso do CT-e</xMotivo>
  <protCTe versao="4.00"><infProt><tpAmb>2</tpAmb><chCTe>{$ch57}</chCTe>
    <dhRecbto>2026-06-01T10:00:00-03:00</dhRecbto><nProt>342260000000020</nProt>
    <cStat>100</cStat><xMotivo>Autorizado o uso do CT-e</xMotivo></infProt></protCTe>
</retCTe>
XML;
        $this->upload('1-pro-lot.xml', $lote)->assertOk();
        $this->assertSame('autorizada', FiscalStatus::where('key', $ch57)->value('category'));
        $this->assertSame(57, (int) FiscalStatus::where('key', $ch57)->value('model'));

        // CT-e OS (model 67) via sit com fallback de filename (topo sem chCTe).
        $ch67 = $this->chave('000000704', '67');
        $sit = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<retConsSitCTe xmlns="http://www.portalfiscal.inf.br/cte" versao="4.00">
  <tpAmb>2</tpAmb><verAplic>TESTE</verAplic><cStat>217</cStat>
  <xMotivo>Rejeicao: CT-e nao consta na base de dados da SEFAZ</xMotivo><cUF>42</cUF>
</retConsSitCTe>
XML;
        $this->upload("{$ch67}-sit.xml", $sit)->assertOk();
        $this->assertSame(67, (int) FiscalStatus::where('key', $ch67)->value('model'));
        $this->assertSame('rejeitada', FiscalStatus::where('key', $ch67)->value('category'));
    }

    public function test_cstat_132_vira_encerrado_e_ponte_grava_no_documento(): void
    {
        $chave = $this->chave('000000705', '58');
        $this->criaDocumento($chave, self::CNPJ); // helper existente (status 100)

        $this->upload("{$chave}-sit.xml", $this->xmlSitMdfe(132, 'Encerrado',
            $this->protMdfe($chave, 132, '2026-06-10T09:00:00-03:00')))->assertOk();

        $this->assertSame('encerrado', FiscalStatus::where('key', $chave)->value('category'));
        $this->assertSame('132', (string) \Illuminate\Support\Facades\DB::table('documents')
            ->where('key', $chave)->value('status_xml'));
    }
}
