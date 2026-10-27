<?php

namespace Tests\Feature\Api;

use App\Livewire\Panel\Documents\Index as DocumentsIndex;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Fora do prazo" não é status do portal: a SEFAZ ainda pode devolver 150/151,
 * então TODA porta de ingestão colapsa (150 -> 100, 151 -> 101).
 *
 * Trava: as duas notas entram colapsadas; os grupos da tela não têm mais os
 * chips "fora do prazo"; e 150/151 saíram de knownCodes(), senão um residual
 * cairia no catch-all e uma nota autorizada apareceria como rejeitada.
 *
 * ⚠️ FiscalStatus::categoryFor() continua mapeando os dois de propósito: o
 * ledger guarda o cStat REAL da SEFAZ (coberto por FiscalStatusModelTest).
 */
class ForaDoPrazoColapsoTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '99887766000166';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local'); // não sujar storage/app/docs com os XMLs de teste
    }

    private function chave(string $nnf): string
    {
        // cUF(2) AAMM(4) CNPJ(14) mod(2) serie(3) nNF(9) tpEmis(1) cNF(8) cDV(1)
        return '42' . '2607' . self::CNPJ . '55' . '001' . $nnf . '1' . '00000043' . '0';
    }

    private function xmlNota(string $chave, string $cStat): string
    {
        $cnpj = self::CNPJ;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe>
    <infNFe Id="NFe{$chave}" versao="4.00">
      <ide><cUF>42</cUF><cNF>00000043</cNF><natOp>VENDA</natOp><mod>55</mod>
        <serie>1</serie><nNF>123</nNF><dhEmi>2026-07-06T10:00:00-03:00</dhEmi>
        <tpNF>1</tpNF><tpAmb>1</tpAmb></ide>
      <emit><CNPJ>{$cnpj}</CNPJ><xNome>EMPRESA TESTE FORA DO PRAZO LTDA</xNome>
        <xFant>TESTE FORA DO PRAZO</xFant>
        <enderEmit><xLgr>RUA TESTE</xLgr><nro>1</nro><xBairro>CENTRO</xBairro>
          <xMun>FLORIANOPOLIS</xMun><UF>SC</UF><CEP>88000000</CEP><fone>4899999999</fone>
        </enderEmit><IE>123456789</IE></emit>
      <total><ICMSTot><vNF>250.00</vNF></ICMSTot></total>
    </infNFe>
  </NFe>
  <protNFe versao="4.00">
    <infProt><tpAmb>1</tpAmb><chNFe>{$chave}</chNFe>
      <dhRecbto>2026-07-06T10:00:01-03:00</dhRecbto><nProt>442260000000003</nProt>
      <cStat>{$cStat}</cStat><xMotivo>Retorno da SEFAZ</xMotivo></infProt>
  </protNFe>
</nfeProc>
XML;
    }

    private function enviaNota(string $chave, string $cStat)
    {
        return $this->post('/api/docs/nfenfce/upload', [
            'key' => 'Sistema',
            'file' => UploadedFile::fake()->createWithContent(
                $chave . '-nfe.xml',
                $this->xmlNota($chave, $cStat)
            ),
        ]);
    }

    /* ------------------------- colapso no import ------------------------- */

    public function test_nota_autorizada_fora_do_prazo_entra_como_autorizada(): void
    {
        $chave = $this->chave('000000301');

        $this->enviaNota($chave, '150')->assertOk()->assertExactJson(['msg' => '100']);

        $doc = DB::table('documents')->where('key', $chave)->first();
        $this->assertNotNull($doc, 'Documento deveria ter sido gravado.');
        $this->assertSame(
            100,
            (int) $doc->status_xml,
            'cStat 150 (autorizada fora do prazo) deve colapsar para 100 (Autorizada).'
        );
    }

    public function test_nota_cancelada_fora_do_prazo_entra_como_cancelada(): void
    {
        $chave = $this->chave('000000302');

        $this->enviaNota($chave, '151')->assertOk()->assertExactJson(['msg' => '100']);

        $doc = DB::table('documents')->where('key', $chave)->first();
        $this->assertNotNull($doc, 'Documento deveria ter sido gravado.');
        $this->assertSame(
            101,
            (int) $doc->status_xml,
            'cStat 151 (cancelada fora do prazo) deve colapsar para 101 (Cancelada).'
        );
    }

    /** A rota de Entrada arquiva pela empresa DESTINATÁRIA — exige o nó <dest>. */
    private function xmlEntrada(string $chave, string $cStat): string
    {
        return str_replace(
            '<total>',
            '<dest><CNPJ>11222333000144</CNPJ><xNome>EMPRESA COMPRADORA LTDA</xNome>'
            . '<enderDest><xLgr>RUA D</xLgr><nro>1</nro><xBairro>CENTRO</xBairro>'
            . '<xMun>FLORIANOPOLIS</xMun><UF>SC</UF><CEP>88000000</CEP></enderDest>'
            . '<IE>123456789</IE></dest><total>',
            $this->xmlNota($chave, $cStat)
        );
    }

    /**
     * Entrada/Compras tem switch próprio na ingestão, duplicado do nfe_nfce —
     * sem este teste dá para colapsar só uma das portas.
     */
    public function test_nota_de_entrada_fora_do_prazo_entra_como_autorizada(): void
    {
        $chave = $this->chave('000000303');

        $this->post('/api/docs/nfe/upload', [
            'key' => 'Sistema',
            'file' => UploadedFile::fake()->createWithContent(
                $chave . '-nfe.xml',
                $this->xmlEntrada($chave, '150')
            ),
        ])->assertOk()->assertExactJson(['msg' => '100']);

        $status = DB::table('documents')->where('key', $chave)->value('status_xml');
        $this->assertSame('100', (string) $status);
    }

    /**
     * O SAT é a única porta sem switch: o status vem do POST e seria gravado
     * cru, furando a regra mesmo com as outras colapsadas.
     */
    public function test_sat_com_status_fora_do_prazo_grava_status_colapsado(): void
    {
        $chave = '42260799887766001665590010000004011000000420';

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<CFe>
  <infCFe Id="CFe{$chave}" versao="0.08">
    <ide><cUF>42</cUF><mod>59</mod><nserieSAT>900004019</nserieSAT><nCFe>000401</nCFe>
      <dEmi>20260701</dEmi><hEmi>100000</hEmi><tpAmb>1</tpAmb></ide>
    <emit><CNPJ>99887766000166</CNPJ><xNome>EMPRESA TESTE SAT LTDA</xNome>
      <enderEmit><xLgr>RUA TESTE</xLgr><nro>1</nro><xBairro>CENTRO</xBairro>
        <xMun>FLORIANOPOLIS</xMun><CEP>88000000</CEP></enderEmit><IE>123456789</IE></emit>
    <total><vCFe>75.00</vCFe></total>
  </infCFe>
</CFe>
XML;

        $this->post('/api/docs/sat/upload', [
            'key' => 'Sistema',
            'sat_status' => '150',
            'file' => UploadedFile::fake()->createWithContent($chave . '-sat.xml', $xml),
        ])->assertOk()->assertExactJson(['msg' => '100']);

        $status = DB::table('documents')->where('key', $chave)->value('status_xml');
        $this->assertSame(
            '100',
            (string) $status,
            'sat_status=150 deve colapsar para 100 — senão fura a regra por outra porta.'
        );
    }

    /* ----------------------- telas sem "fora do prazo" ----------------------- */

    public function test_grupos_de_status_nao_tem_mais_fora_do_prazo(): void
    {
        $grupos = DocumentsIndex::statusGroups();

        $this->assertArrayNotHasKey('autorizada_fp', $grupos);
        $this->assertArrayNotHasKey('cancelada_fp', $grupos);

        foreach ($grupos as $chave => $grupo) {
            $this->assertStringNotContainsStringIgnoringCase(
                'fora do prazo',
                $grupo['label'],
                "O grupo '{$chave}' ainda rotula 'fora do prazo'."
            );
        }
    }

    public function test_tipos_nao_referenciam_grupos_fora_do_prazo(): void
    {
        $grupos = DocumentsIndex::statusGroups();

        foreach (DocumentsIndex::types() as $tipo => $config) {
            foreach ($config['statuses'] as $status) {
                $this->assertArrayHasKey(
                    $status,
                    $grupos,
                    "O tipo '{$tipo}' aponta para o grupo '{$status}', que não existe."
                );
            }
        }
    }

    public function test_150_e_151_sairam_dos_codigos_conhecidos(): void
    {
        $codigos = DocumentsIndex::knownCodes();

        $this->assertNotContains(150, $codigos);
        $this->assertNotContains(151, $codigos);

        // Garante que os códigos que os substituem continuam conhecidos.
        $this->assertContains(100, $codigos);
        $this->assertContains(101, $codigos);
    }
}
