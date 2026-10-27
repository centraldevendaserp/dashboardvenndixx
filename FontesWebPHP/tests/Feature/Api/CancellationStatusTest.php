<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Vínculo entre o evento de cancelamento e o status da nota. O cancelamento
 * chega como evento separado, depois da nota, e tem de virar o status_xml.
 *
 * Trava: nota e evento entram com msg 100; cancelamento vira a nota para 101;
 * CC-e não altera o status; nota que chega depois do evento já entra como 101.
 */
class CancellationStatusTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '99887766000155';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local'); // não sujar storage/app/docs com os XMLs de teste
    }

    private function chave(string $nnf = '000000123'): string
    {
        // cUF(2) AAMM(4) CNPJ(14) mod(2) serie(3) nNF(9) tpEmis(1) cNF(8) cDV(1)
        return '42' . '2607' . self::CNPJ . '55' . '001' . $nnf . '1' . '00000042' . '0';
    }

    private function xmlNota(string $chave): string
    {
        $cnpj = self::CNPJ;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe>
    <infNFe Id="NFe{$chave}" versao="4.00">
      <ide><cUF>42</cUF><cNF>00000042</cNF><natOp>VENDA</natOp><mod>55</mod>
        <serie>1</serie><nNF>123</nNF><dhEmi>2026-07-06T10:00:00-03:00</dhEmi>
        <tpNF>1</tpNF><tpAmb>1</tpAmb></ide>
      <emit><CNPJ>{$cnpj}</CNPJ><xNome>EMPRESA TESTE CANCELAMENTO LTDA</xNome>
        <xFant>TESTE CANCELAMENTO</xFant>
        <enderEmit><xLgr>RUA TESTE</xLgr><nro>1</nro><xBairro>CENTRO</xBairro>
          <xMun>FLORIANOPOLIS</xMun><UF>SC</UF><CEP>88000000</CEP><fone>4899999999</fone>
        </enderEmit><IE>123456789</IE></emit>
      <total><ICMSTot><vNF>150.00</vNF></ICMSTot></total>
    </infNFe>
  </NFe>
  <protNFe versao="4.00">
    <infProt><tpAmb>1</tpAmb><chNFe>{$chave}</chNFe>
      <dhRecbto>2026-07-06T10:00:01-03:00</dhRecbto><nProt>442260000000001</nProt>
      <cStat>100</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo></infProt>
  </protNFe>
</nfeProc>
XML;
    }

    private function xmlEvento(string $chave, string $tpEvento = '110111', string $cStat = '135'): string
    {
        $cnpj = self::CNPJ;
        $det = $tpEvento === '110110'
            ? '<descEvento>Carta de Correcao</descEvento><xCorrecao>Correcao de teste com mais de quinze caracteres</xCorrecao>'
            : '<descEvento>Cancelamento</descEvento><nProt>442260000000001</nProt><xJust>Cancelamento de teste automatizado</xJust>';

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<procEventoNFe versao="1.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <evento versao="1.00">
    <infEvento Id="ID{$tpEvento}{$chave}01">
      <cOrgao>42</cOrgao><tpAmb>1</tpAmb><CNPJ>{$cnpj}</CNPJ><chNFe>{$chave}</chNFe>
      <dhEvento>2026-07-06T11:00:00-03:00</dhEvento><tpEvento>{$tpEvento}</tpEvento>
      <nSeqEvento>1</nSeqEvento><verEvento>1.00</verEvento>
      <detEvento versao="1.00">{$det}</detEvento>
    </infEvento>
  </evento>
  <retEvento versao="1.00">
    <infEvento><tpAmb>1</tpAmb><verAplic>TESTE</verAplic><cOrgao>42</cOrgao>
      <cStat>{$cStat}</cStat><xMotivo>Evento registrado</xMotivo><chNFe>{$chave}</chNFe>
      <tpEvento>{$tpEvento}</tpEvento><xEvento>Evento</xEvento><nSeqEvento>1</nSeqEvento>
      <dhRegEvento>2026-07-06T11:00:01-03:00</dhRegEvento><nProt>442260000000002</nProt>
    </infEvento>
  </retEvento>
</procEventoNFe>
XML;
    }

    private function enviaNota(string $chave)
    {
        return $this->post('/api/docs/nfenfce/upload', [
            'key' => 'Sistema',
            'file' => UploadedFile::fake()->createWithContent($chave . '-nfe.xml', $this->xmlNota($chave)),
        ]);
    }

    private function enviaEvento(string $chave, string $tpEvento = '110111', string $cStat = '135')
    {
        return $this->post('/api/docs/eventos/nfenfce/upload', [
            'key' => 'Sistema',
            'file' => UploadedFile::fake()->createWithContent($chave . '-eve.xml', $this->xmlEvento($chave, $tpEvento, $cStat)),
        ]);
    }

    /* ------------------- caracterização (comportamento atual) ------------------- */

    public function test_upload_de_nota_autorizada_grava_documento_com_status_100(): void
    {
        $chave = $this->chave('000000201');

        $this->enviaNota($chave)->assertOk()->assertExactJson(['msg' => '100']);

        $doc = DB::table('documents')->where('key', $chave)->first();
        $this->assertNotNull($doc, 'Documento deveria ter sido gravado.');
        $this->assertSame(100, (int) $doc->status_xml);
    }

    public function test_upload_de_evento_de_cancelamento_grava_em_event_documents(): void
    {
        $chave = $this->chave('000000202');

        $this->enviaEvento($chave)->assertOk()->assertExactJson(['msg' => '100']);

        $evento = DB::table('event_documents')->where('nfe_key', $chave)->first();
        $this->assertNotNull($evento, 'Evento deveria ter sido gravado.');
        $this->assertSame(110111, (int) $evento->event_type);
        $this->assertSame(135, (int) $evento->event_status);
    }

    /* --------------------- comportamento novo (o conserto) ---------------------- */

    public function test_evento_de_cancelamento_atualiza_nota_para_cancelada(): void
    {
        $chave = $this->chave('000000203');

        $this->enviaNota($chave)->assertOk();
        $this->enviaEvento($chave)->assertOk()->assertExactJson(['msg' => '100']);

        $doc = DB::table('documents')->where('key', $chave)->first();
        $this->assertSame(
            101,
            (int) $doc->status_xml,
            'Nota com evento de cancelamento (110111/cStat 135) deveria virar status 101 (Cancelada).'
        );
    }

    public function test_carta_de_correcao_nao_altera_status_da_nota(): void
    {
        $chave = $this->chave('000000204');

        $this->enviaNota($chave)->assertOk();
        $this->enviaEvento($chave, '110110', '135')->assertOk();

        $doc = DB::table('documents')->where('key', $chave)->first();
        $this->assertSame(100, (int) $doc->status_xml, 'CC-e não pode mudar o status da nota.');
    }

    public function test_evento_rejeitado_nao_altera_status_da_nota(): void
    {
        $chave = $this->chave('000000205');

        $this->enviaNota($chave)->assertOk();
        // cStat de rejeição do evento (ex.: 573 duplicidade) — não cancela a nota
        $this->enviaEvento($chave, '110111', '573')->assertOk();

        $doc = DB::table('documents')->where('key', $chave)->first();
        $this->assertSame(100, (int) $doc->status_xml, 'Evento rejeitado não pode cancelar a nota.');
    }

    public function test_nota_que_chega_depois_do_cancelamento_ja_entra_cancelada(): void
    {
        $chave = $this->chave('000000206');

        // ordem invertida: evento primeiro (nota falhou no ciclo e reenviou depois)
        $this->enviaEvento($chave)->assertOk();
        $this->enviaNota($chave)->assertOk();

        $doc = DB::table('documents')->where('key', $chave)->first();
        $this->assertNotNull($doc);
        $this->assertSame(
            101,
            (int) $doc->status_xml,
            'Nota que chega após o evento de cancelamento deveria entrar já como 101.'
        );
    }
}
