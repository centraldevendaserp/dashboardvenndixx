<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ingestão de CT-e (57) e MDF-e (58) pelo caminho real do agente (key +
 * multipart `file`): XML no layout -> msg 100 -> linha com modelo, valor e
 * status certos. Namespace igual ao do XML real.
 */
class CteMdfeUploadTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '77665544000133';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function envia(string $route, string $xml, string $filename)
    {
        return $this->post("/api/docs/{$route}/upload", [
            'key'  => 'Sistema',
            'file' => UploadedFile::fake()->createWithContent($filename, $xml),
        ]);
    }

    private function xmlCte(string $chave, string $cStat = '100'): string
    {
        $cnpj = self::CNPJ;
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cteProc versao="4.00" xmlns="http://www.portalfiscal.inf.br/cte">
  <CTe><infCte Id="CTe{$chave}" versao="4.00">
    <ide><cUF>42</cUF><mod>57</mod><serie>1</serie><nCT>701</nCT>
      <dhEmi>2027-03-15T10:00:00-03:00</dhEmi><tpAmb>1</tpAmb></ide>
    <emit><CNPJ>{$cnpj}</CNPJ><IE>123456789</IE><xNome>TRANSPORTES CTE LTDA</xNome><xFant>CTE</xFant>
      <enderEmit><xLgr>R</xLgr><nro>1</nro><xBairro>C</xBairro><xMun>FLN</xMun><UF>SC</UF>
        <CEP>88000000</CEP><fone>4899999999</fone></enderEmit></emit>
    <vPrest><vTPrest>350.00</vTPrest></vPrest>
  </infCte></CTe>
  <protCTe versao="4.00"><infProt><tpAmb>1</tpAmb><chCTe>{$chave}</chCTe>
    <nProt>442270000000701</nProt><cStat>{$cStat}</cStat><xMotivo>Autorizado</xMotivo></infProt></protCTe>
</cteProc>
XML;
    }

    private function xmlMdfe(string $chave, string $cStat = '100'): string
    {
        $cnpj = self::CNPJ;
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<mdfeProc versao="3.00" xmlns="http://www.portalfiscal.inf.br/mdfe">
  <MDFe><infMDFe Id="MDFe{$chave}" versao="3.00">
    <ide><cUF>42</cUF><mod>58</mod><serie>1</serie><nMDF>801</nMDF>
      <dhEmi>2027-03-15T10:00:00-03:00</dhEmi><tpAmb>1</tpAmb></ide>
    <emit><CNPJ>{$cnpj}</CNPJ><IE>123456789</IE><xNome>TRANSPORTES MDFE LTDA</xNome><xFant>MDFE</xFant>
      <enderEmit><xLgr>R</xLgr><nro>1</nro><xBairro>C</xBairro><xMun>FLN</xMun><UF>SC</UF>
        <CEP>88000000</CEP><fone>4899999999</fone></enderEmit></emit>
    <tot><vCarga>5000.00</vCarga></tot>
  </infMDFe></MDFe>
  <protMDFe versao="3.00"><infProt><tpAmb>1</tpAmb><chMDFe>{$chave}</chMDFe>
    <nProt>442270000000801</nProt><cStat>{$cStat}</cStat><xMotivo>Autorizado</xMotivo></infProt></protMDFe>
</mdfeProc>
XML;
    }

    /* ------------------------------ CT-e (57) ------------------------------ */

    public function test_cte_autorizado_grava_documento_modelo_57(): void
    {
        $chave = '42260777665544000133570010000007011000000570';

        $this->envia('cte', $this->xmlCte($chave, '100'), 'cte.xml')
            ->assertOk()->assertExactJson(['msg' => '100']);

        $row = DB::table('documents')->where('key', $chave)->first();
        $this->assertNotNull($row, 'A CT-e deveria ter sido gravada.');
        $this->assertSame(57, (int) $row->model);
        $this->assertSame('100', (string) $row->status_xml);
        $this->assertSame(self::CNPJ, $row->cnpj_cpf);
        $this->assertEquals(350.00, (float) $row->vNF);
    }

    public function test_cte_cancelada_grava_status_101(): void
    {
        $chave = '42260777665544000133570010000007021000000570';

        // cStat 135 (evento de cancelamento vinculado) deve ser mapeado para 101.
        $this->envia('cte', $this->xmlCte($chave, '135'), 'cte.xml')
            ->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertSame('101', (string) DB::table('documents')->where('key', $chave)->value('status_xml'));
    }

    /* ------------------------------ MDF-e (58) ----------------------------- */

    public function test_mdfe_autorizado_grava_documento_modelo_58(): void
    {
        $chave = '42260777665544000133580010000008011000000580';

        $this->envia('mdfe', $this->xmlMdfe($chave, '100'), 'mdfe.xml')
            ->assertOk()->assertExactJson(['msg' => '100']);

        $row = DB::table('documents')->where('key', $chave)->first();
        $this->assertNotNull($row, 'A MDF-e deveria ter sido gravada.');
        $this->assertSame(58, (int) $row->model);
        $this->assertSame('100', (string) $row->status_xml);
        $this->assertSame(self::CNPJ, $row->cnpj_cpf);
        $this->assertEquals(5000.00, (float) $row->vNF);
    }

    public function test_mdfe_cancelada_grava_status_101(): void
    {
        $chave = '42260777665544000133580010000008021000000580';

        $this->envia('mdfe', $this->xmlMdfe($chave, '135'), 'mdfe.xml')
            ->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertSame('101', (string) DB::table('documents')->where('key', $chave)->value('status_xml'));
    }

    /* --------------------- "fora do prazo" colapsa --------------------- */

    public function test_cte_autorizada_fora_do_prazo_grava_status_100(): void
    {
        $chave = '42260777665544000133570010000007031000000570';

        $this->envia('cte', $this->xmlCte($chave, '150'), 'cte.xml')
            ->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertSame('100', (string) DB::table('documents')->where('key', $chave)->value('status_xml'));
    }

    public function test_cte_cancelada_fora_do_prazo_grava_status_101(): void
    {
        $chave = '42260777665544000133570010000007041000000570';

        $this->envia('cte', $this->xmlCte($chave, '151'), 'cte.xml')
            ->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertSame('101', (string) DB::table('documents')->where('key', $chave)->value('status_xml'));
    }

    public function test_mdfe_autorizada_fora_do_prazo_grava_status_100(): void
    {
        $chave = '42260777665544000133580010000008031000000580';

        $this->envia('mdfe', $this->xmlMdfe($chave, '150'), 'mdfe.xml')
            ->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertSame('100', (string) DB::table('documents')->where('key', $chave)->value('status_xml'));
    }

    public function test_mdfe_cancelada_fora_do_prazo_grava_status_101(): void
    {
        $chave = '42260777665544000133580010000008041000000580';

        $this->envia('mdfe', $this->xmlMdfe($chave, '151'), 'mdfe.xml')
            ->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertSame('101', (string) DB::table('documents')->where('key', $chave)->value('status_xml'));
    }
}
