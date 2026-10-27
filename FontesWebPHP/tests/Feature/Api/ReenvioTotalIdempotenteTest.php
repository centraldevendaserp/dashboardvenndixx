<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `[config] reenviar_tudo=1` no .ini zera o dedup do agente e faz todos os XMLs
 * subirem de novo — reimportar não pode duplicar nada.
 *
 * Cobre evento, inutilização e o ledger `fiscal_status`, que o reenvio também
 * refaz. Cada teste envia duas vezes o mesmo arquivo e cobra 1 linha.
 */
class ReenvioTotalIdempotenteTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '99887766000155';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** cUF(2) AAMM(4) CNPJ(14) mod(2) série(3) nNF(9) tpEmis(1) cNF(8) cDV(1) */
    private function chave(string $nnf): string
    {
        return '42' . '2607' . self::CNPJ . '55' . '001' . $nnf . '1' . '00000042' . '0';
    }

    /* --------------------------- cancelamento --------------------------- */

    private function xmlCancelamento(string $chave, string $protocolo): string
    {
        $cnpj = self::CNPJ;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<procEventoNFe versao="1.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <evento versao="1.00">
    <infEvento Id="ID110111{$chave}01">
      <cOrgao>91</cOrgao><tpAmb>1</tpAmb><CNPJ>{$cnpj}</CNPJ><chNFe>{$chave}</chNFe>
      <dhEvento>2026-07-06T11:00:00-03:00</dhEvento><tpEvento>110111</tpEvento>
      <nSeqEvento>1</nSeqEvento><verEvento>1.00</verEvento>
      <detEvento versao="1.00">
        <descEvento>Cancelamento</descEvento><nProt>{$protocolo}</nProt>
        <xJust>Teste de reenvio total</xJust>
      </detEvento>
    </infEvento>
  </evento>
  <retEvento versao="1.00">
    <infEvento><tpAmb>1</tpAmb><verAplic>TESTE</verAplic><cOrgao>91</cOrgao>
      <cStat>135</cStat><xMotivo>Evento registrado</xMotivo><chNFe>{$chave}</chNFe>
      <tpEvento>110111</tpEvento><xEvento>Cancelamento</xEvento><nSeqEvento>1</nSeqEvento>
      <dhRegEvento>2026-07-06T11:00:01-03:00</dhRegEvento><nProt>{$protocolo}</nProt>
    </infEvento>
  </retEvento>
</procEventoNFe>
XML;
    }

    public function test_reenvio_do_cancelamento_nao_duplica(): void
    {
        $chave = $this->chave('000950001');
        $prot = '442260000950001';
        $xml = $this->xmlCancelamento($chave, $prot);

        foreach ([1, 2] as $vez) {
            $this->post('/api/docs/eventos/nfenfce/upload', [
                'key'  => 'Sistema',
                'file' => UploadedFile::fake()->createWithContent("{$chave}-procEventoNFe.xml", $xml),
            ])->assertJson(['msg' => '100'], false);
        }

        $this->assertSame(
            1,
            DB::table('event_documents')->where('nfe_key', $chave)->where('protocol_number', $prot)->count(),
            'o mesmo cancelamento reenviado virou duas linhas'
        );
    }

    /* --------------------------- inutilização --------------------------- */

    private function xmlInutilizacao(string $protocolo, int $numero): string
    {
        $cnpj = self::CNPJ;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<ProcInutNFe versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <inutNFe versao="4.00">
    <infInut Id="ID42267998877660001555500100000{$numero}00000{$numero}">
      <tpAmb>1</tpAmb><xServ>INUTILIZAR</xServ><cUF>42</cUF><ano>26</ano>
      <CNPJ>{$cnpj}</CNPJ><mod>55</mod><serie>1</serie>
      <nNFIni>{$numero}</nNFIni><nNFFin>{$numero}</nNFFin>
      <xJust>Teste de reenvio total do agente</xJust>
    </infInut>
  </inutNFe>
  <retInutNFe versao="4.00">
    <infInut><tpAmb>1</tpAmb><verAplic>TESTE</verAplic><cStat>102</cStat>
      <xMotivo>Inutilizacao de numero homologado</xMotivo><cUF>42</cUF><ano>26</ano>
      <CNPJ>{$cnpj}</CNPJ><mod>55</mod><serie>1</serie>
      <nNFIni>{$numero}</nNFIni><nNFFin>{$numero}</nNFFin>
      <dhRecbto>2026-07-06T11:00:01-03:00</dhRecbto><nProt>{$protocolo}</nProt>
    </infInut>
  </retInutNFe>
</ProcInutNFe>
XML;
    }

    public function test_reenvio_da_inutilizacao_nao_duplica(): void
    {
        $prot = '442260000950011';
        $xml = $this->xmlInutilizacao($prot, 950011);

        foreach ([1, 2] as $vez) {
            $this->post('/api/docs/inutilizacao/nfenfce/upload', [
                'key'  => 'Sistema',
                'file' => UploadedFile::fake()->createWithContent("{$prot}-procInutNFe.xml", $xml),
            ])->assertJson(['msg' => '100'], false);
        }

        $this->assertSame(
            1,
            DB::table('disable_documents')->where('cnpj', self::CNPJ)->where('protocol_number', $prot)->count(),
            'a mesma inutilizacao reenviada virou duas linhas'
        );
    }

    /* ------------------------ ledger fiscal_status ----------------------- */

    /**
     * O envelope de status também é refeito no reenvio total. O ledger é 1 linha
     * por CHAVE: reenviar atualiza, nunca acrescenta.
     */
    public function test_reenvio_do_status_nao_duplica_a_chave(): void
    {
        $chave = $this->chave('000950021');
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<retConsSitNFe versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <tpAmb>1</tpAmb><verAplic>TESTE</verAplic><cStat>217</cStat>
  <xMotivo>NF-e nao consta na base de dados da SEFAZ</xMotivo>
  <cUF>42</cUF><chNFe>{$chave}</chNFe>
</retConsSitNFe>
XML;

        foreach ([1, 2] as $vez) {
            $this->post('/api/docs/status/upload', [
                'key'  => 'Sistema',
                'file' => UploadedFile::fake()->createWithContent("{$chave}-sit.xml", $xml),
            ])->assertJson(['msg' => '100'], false);
        }

        $this->assertSame(
            1,
            DB::table('fiscal_status')->where('key', $chave)->count(),
            'a mesma chave reenviada virou duas linhas no ledger'
        );
    }

    /* ------------------------------ empresa ------------------------------ */

    /**
     * O import cadastra a empresa quando ela não existe. Reenviar não pode
     * cadastrar de novo: CNPJ duplicado quebraria o escopo por empresa.
     */
    public function test_reenvio_nao_duplica_a_empresa(): void
    {
        $chave = $this->chave('000950031');
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe><infNFe versao="4.00" Id="NFe{$chave}">
    <ide><cUF>42</cUF><natOp>VENDA</natOp><mod>55</mod><serie>1</serie>
      <nNF>950031</nNF><dhEmi>2026-07-06T10:00:00-03:00</dhEmi><tpNF>1</tpNF>
      <tpAmb>1</tpAmb><tpEmis>1</tpEmis></ide>
    <emit><CNPJ>99887766000155</CNPJ><xNome>EMPRESA DO REENVIO LTDA</xNome>
      <xFant>REENVIO</xFant>
      <enderEmit><xLgr>RUA X</xLgr><nro>1</nro><xBairro>CENTRO</xBairro>
        <cMun>4204202</cMun><xMun>TESTE</xMun><UF>SC</UF><CEP>88000000</CEP>
        <fone>4830000000</fone></enderEmit>
      <IE>123456789</IE></emit>
    <total><ICMSTot><vNF>10.00</vNF></ICMSTot></total>
  </infNFe></NFe>
  <protNFe versao="4.00"><infProt><tpAmb>1</tpAmb><chNFe>{$chave}</chNFe>
    <dhRecbto>2026-07-06T10:00:05-03:00</dhRecbto><nProt>142260000950031</nProt>
    <cStat>100</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo></infProt></protNFe>
</nfeProc>
XML;

        foreach ([1, 2] as $vez) {
            $this->post('/api/docs/nfenfce/upload', [
                'key'  => 'Sistema',
                'file' => UploadedFile::fake()->createWithContent("{$chave}-nfe.xml", $xml),
            ])->assertJson(['msg' => '100'], false);
        }

        $this->assertSame(1, DB::table('documents')->where('key', $chave)->count(), 'nota duplicada');
        $this->assertSame(1, DB::table('companies')->where('cnpj_cpf', self::CNPJ)->count(), 'empresa duplicada');
    }
}
