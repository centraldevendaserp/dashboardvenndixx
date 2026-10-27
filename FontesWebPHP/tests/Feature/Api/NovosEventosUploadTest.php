<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Além de cancelamento e CC-e, o agente varre manifestação do destinatário
 * (210xxx) e pastas de transição — tudo chega no mesmo endpoint, que rotula
 * pelo tpEvento:
 *  - 2102xx -> "Manifestacao" (sem xJust no detEvento);
 *  - evento desconhecido -> "Evento", sem quebrar;
 *  - manifestação nunca altera o status da nota.
 */
class NovosEventosUploadTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '99887766000155';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function chave(string $nnf, string $cnpj = self::CNPJ): string
    {
        // cUF(2) AAMM(4) CNPJ(14) mod(2) serie(3) nNF(9) tpEmis(1) cNF(8) cDV(1)
        return '42' . '2607' . $cnpj . '55' . '001' . $nnf . '1' . '00000042' . '0';
    }

    private function xmlEvento(string $chave, string $tpEvento, string $det): string
    {
        $cnpj = self::CNPJ;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<procEventoNFe versao="1.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <evento versao="1.00">
    <infEvento Id="ID{$tpEvento}{$chave}01">
      <cOrgao>91</cOrgao><tpAmb>1</tpAmb><CNPJ>{$cnpj}</CNPJ><chNFe>{$chave}</chNFe>
      <dhEvento>2026-07-06T11:00:00-03:00</dhEvento><tpEvento>{$tpEvento}</tpEvento>
      <nSeqEvento>1</nSeqEvento><verEvento>1.00</verEvento>
      <detEvento versao="1.00">{$det}</detEvento>
    </infEvento>
  </evento>
  <retEvento versao="1.00">
    <infEvento><tpAmb>1</tpAmb><verAplic>TESTE</verAplic><cOrgao>91</cOrgao>
      <cStat>135</cStat><xMotivo>Evento registrado</xMotivo><chNFe>{$chave}</chNFe>
      <tpEvento>{$tpEvento}</tpEvento><xEvento>Evento</xEvento><nSeqEvento>1</nSeqEvento>
      <dhRegEvento>2026-07-06T11:00:01-03:00</dhRegEvento><nProt>442260000000077</nProt>
    </infEvento>
  </retEvento>
</procEventoNFe>
XML;
    }

    private function enviaEvento(string $chave, string $tpEvento, string $det)
    {
        return $this->post('/api/docs/eventos/nfenfce/upload', [
            'key' => 'Sistema',
            'file' => UploadedFile::fake()->createWithContent(
                $tpEvento . $chave . '-procEventoNFe.xml',
                $this->xmlEvento($chave, $tpEvento, $det)
            ),
        ]);
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
      <emit><CNPJ>{$cnpj}</CNPJ><xNome>EMPRESA TESTE MANIFESTACAO LTDA</xNome>
        <xFant>TESTE MANIFESTACAO</xFant>
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

    public function test_manifestacao_ciencia_e_aceita_e_rotulada_como_manifestacao(): void
    {
        $chave = $this->chave('000000301');

        $this->enviaEvento($chave, '210210', '<descEvento>Ciencia da Operacao</descEvento>')
            ->assertOk()
            ->assertExactJson(['msg' => '100']);

        $evento = DB::table('event_documents')->where('nfe_key', $chave)->first();
        $this->assertNotNull($evento, 'Manifestação deveria ter sido gravada.');
        $this->assertSame(210210, (int) $evento->event_type);
        $this->assertSame('Ciencia da Operacao', (string) $evento->event_desc);
        $this->assertSame('', (string) $evento->justification, 'Manifestação não tem xJust — deve gravar vazio.');
        $this->assertStringContainsString(
            '/Manifestacao/',
            (string) $evento->path_xml,
            'Manifestação não pode ser arquivada com rótulo "Cancelamento".'
        );
    }

    public function test_manifestacao_nao_altera_status_de_nota_existente(): void
    {
        $chave = $this->chave('000000302');

        $this->post('/api/docs/nfenfce/upload', [
            'key' => 'Sistema',
            'file' => UploadedFile::fake()->createWithContent($chave . '-nfe.xml', $this->xmlNota($chave)),
        ])->assertOk();

        $this->enviaEvento($chave, '210210', '<descEvento>Ciencia da Operacao</descEvento>')->assertOk();

        $doc = DB::table('documents')->where('key', $chave)->first();
        $this->assertSame(100, (int) $doc->status_xml, 'Manifestação não pode cancelar a nota.');
    }

    public function test_evento_desconhecido_e_aceito_com_rotulo_generico(): void
    {
        $chave = $this->chave('000000303');

        // EPEC (110140): sem xJust/xCorrecao no detEvento — não pode quebrar nem virar "Cancelamento"
        $this->enviaEvento($chave, '110140', '<descEvento>EPEC</descEvento>')
            ->assertOk()
            ->assertExactJson(['msg' => '100']);

        $evento = DB::table('event_documents')->where('nfe_key', $chave)->first();
        $this->assertNotNull($evento);
        $this->assertSame(110140, (int) $evento->event_type);
        $this->assertSame('', (string) $evento->justification);
        $this->assertStringContainsString('/Evento/', (string) $evento->path_xml);
    }
}
