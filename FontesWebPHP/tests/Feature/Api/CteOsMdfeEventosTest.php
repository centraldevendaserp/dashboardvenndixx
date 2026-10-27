<?php

namespace Tests\Feature\Api;

use App\Livewire\Panel\Documents\Index as DocumentsIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Trava três pontos do contrato de CT-e/MDF-e:
 *  - CT-e OS (67) usa as pastas do CT-e e entra pelo endpoint dele;
 *  - evento de MDF-e: encerramento (110112) vira a nota para 132 e
 *    cancelamento (110111) para 101;
 *  - evento de CT-e fora de cancelamento/CC-e não quebra nem vira "Cancelamento".
 */
class CteOsMdfeEventosTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '55443322000111';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function envia(string $rota, string $xml, string $filename)
    {
        return $this->post("/api/docs/{$rota}/upload", [
            'key' => 'Sistema',
            'file' => UploadedFile::fake()->createWithContent($filename, $xml),
        ]);
    }

    /* ------------------------------ CT-e OS (67) ------------------------------ */

    private function xmlCteOs(string $chave): string
    {
        $cnpj = self::CNPJ;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cteOSProc versao="4.00" xmlns="http://www.portalfiscal.inf.br/cte">
  <CTeOS><infCte Id="CTe{$chave}" versao="4.00">
    <ide><cUF>42</cUF><mod>67</mod><serie>1</serie><nCT>901</nCT>
      <dhEmi>2027-03-16T10:00:00-03:00</dhEmi><tpAmb>1</tpAmb></ide>
    <emit><CNPJ>{$cnpj}</CNPJ><IE>123456789</IE><xNome>TRANSPORTES CTEOS LTDA</xNome><xFant>CTEOS</xFant>
      <enderEmit><xLgr>R</xLgr><nro>1</nro><xBairro>C</xBairro><xMun>FLN</xMun><UF>SC</UF>
        <CEP>88000000</CEP><fone>4899999999</fone></enderEmit></emit>
    <vPrest><vTPrest>420.00</vTPrest></vPrest>
  </infCte></CTeOS>
  <protCTe versao="4.00"><infProt><tpAmb>1</tpAmb><chCTe>{$chave}</chCTe>
    <nProt>442270000000901</nProt><cStat>100</cStat><xMotivo>Autorizado</xMotivo></infProt></protCTe>
</cteOSProc>
XML;
    }

    public function test_cte_os_e_aceito_no_endpoint_de_cte_com_modelo_67(): void
    {
        $chave = '42270355443322000111670010000009011000000670';

        $this->envia('cte', $this->xmlCteOs($chave), 'cteos.xml')
            ->assertOk()->assertExactJson(['msg' => '100']);

        $row = DB::table('documents')->where('key', $chave)->first();
        $this->assertNotNull($row, 'O CT-e OS deveria ter sido gravado.');
        $this->assertSame(67, (int) $row->model);
        $this->assertEquals(420.00, (float) $row->vNF);
    }

    public function test_aba_cte_do_painel_mostra_tambem_o_modelo_67(): void
    {
        $chave = '42270355443322000111670010000009021000000671';
        $this->envia('cte', $this->xmlCteOs($chave), 'cteos2.xml')->assertOk();

        $admin = User::factory()->create(['is_admin' => 'S']);
        $this->actingAs($admin);

        // a aba CT-e abre no mês corrente; zera o período (é teste de INCLUSÃO
        // do modelo 67, não de período) para não filtrar o documento de teste.
        $component = Livewire::test(DocumentsIndex::class, ['type' => 'cte'])
            ->set('first_date', null)->set('last_date', null)
            ->instance();
        $baseQuery = new \ReflectionMethod($component, 'baseQuery');
        $keys = $baseQuery->invoke($component)->pluck('key');

        $this->assertTrue($keys->contains($chave), 'A aba CT-e deveria listar o CT-e OS (67).');
    }

    /* ---------------------------- eventos de MDF-e ---------------------------- */

    private function xmlEventoMdfe(string $chave, string $tpEvento, string $detInterno): string
    {
        $cnpj = self::CNPJ;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<procEventoMDFe versao="3.00" xmlns="http://www.portalfiscal.inf.br/mdfe">
  <eventoMDFe versao="3.00">
    <infEvento Id="ID{$tpEvento}{$chave}01">
      <cOrgao>42</cOrgao><tpAmb>1</tpAmb><CNPJ>{$cnpj}</CNPJ><chMDFe>{$chave}</chMDFe>
      <dhEvento>2027-03-20T11:00:00-03:00</dhEvento><tpEvento>{$tpEvento}</tpEvento>
      <nSeqEvento>1</nSeqEvento>
      <detEvento versaoEvento="3.00">{$detInterno}</detEvento>
    </infEvento>
  </eventoMDFe>
  <retEventoMDFe versao="3.00">
    <infEvento><tpAmb>1</tpAmb><verAplic>TESTE</verAplic><cOrgao>42</cOrgao>
      <cStat>135</cStat><xMotivo>Evento registrado</xMotivo><chMDFe>{$chave}</chMDFe>
      <tpEvento>{$tpEvento}</tpEvento><nSeqEvento>1</nSeqEvento>
      <dhRegEvento>2027-03-20T11:00:01-03:00</dhRegEvento><nProt>942270000000123</nProt></infEvento>
  </retEventoMDFe>
</procEventoMDFe>
XML;
    }

    private function criaMdfe(string $chave): void
    {
        DB::table('documents')->insert([
            'cnpj_cpf' => self::CNPJ,
            'ie' => '123456789',
            'model' => 58,
            'series' => 1,
            'number' => 801,
            'key' => $chave,
            'month_year' => '202703',
            'issue_dh' => '2027-03-15',
            'path_xml' => '/docs/teste-mdfe.xml',
            'protocol' => '442270000000801',
            'environment_type' => '1',
            'status_xml' => '100',
            'vNF' => 0,
        ]);
    }

    public function test_encerramento_de_mdfe_grava_evento_e_vira_nota_para_132(): void
    {
        $chave = '42270355443322000111580010000008011000000580';
        $this->criaMdfe($chave);

        $det = '<evEncMDFe><descEvento>Encerramento</descEvento><nProt>442270000000801</nProt>'
            . '<dtEnc>2027-03-20</dtEnc><cMun>4205407</cMun></evEncMDFe>';

        $this->envia('eventos/mdfe', $this->xmlEventoMdfe($chave, '110112', $det), 'enc.xml')
            ->assertOk()->assertExactJson(['msg' => '100']);

        $evento = DB::table('event_documents')->where('nfe_key', $chave)->first();
        $this->assertNotNull($evento, 'O encerramento deveria ter sido gravado.');
        $this->assertSame(110112, (int) $evento->event_type);
        $this->assertSame(58, (int) $evento->model);

        $doc = DB::table('documents')->where('key', $chave)->first();
        $this->assertSame(132, (int) $doc->status_xml, 'MDF-e encerrado deveria virar status 132.');
    }

    public function test_cancelamento_de_mdfe_vira_nota_para_101(): void
    {
        $chave = '42270355443322000111580010000008021000000581';
        $this->criaMdfe($chave);

        $det = '<evCancMDFe><descEvento>Cancelamento</descEvento><nProt>442270000000801</nProt>'
            . '<xJust>Cancelamento de teste automatizado</xJust></evCancMDFe>';

        $this->envia('eventos/mdfe', $this->xmlEventoMdfe($chave, '110111', $det), 'canc.xml')
            ->assertOk()->assertExactJson(['msg' => '100']);

        $doc = DB::table('documents')->where('key', $chave)->first();
        $this->assertSame(101, (int) $doc->status_xml, 'MDF-e cancelado deveria virar status 101.');

        $evento = DB::table('event_documents')->where('nfe_key', $chave)->first();
        $this->assertSame('CANCELAMENTO DE TESTE AUTOMATIZADO', (string) $evento->justification);
    }

    public function test_rota_de_eventos_mdfe_exige_chave(): void
    {
        $this->post('/api/docs/eventos/mdfe/upload', ['key' => 'errada'])
            ->assertStatus(403);
    }

    /* ------------------------ eventos de CT-e (rotulagem) ------------------------ */

    private function xmlEventoCte(string $chave, string $tpEvento, string $det): string
    {
        $cnpj = self::CNPJ;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<procEventoCTe versao="4.00" xmlns="http://www.portalfiscal.inf.br/cte">
  <eventoCTe versao="4.00">
    <infEvento Id="ID{$tpEvento}{$chave}01">
      <cOrgao>42</cOrgao><tpAmb>1</tpAmb><CNPJ>{$cnpj}</CNPJ><chCTe>{$chave}</chCTe>
      <dhEvento>2027-03-21T11:00:00-03:00</dhEvento><tpEvento>{$tpEvento}</tpEvento>
      <nSeqEvento>1</nSeqEvento>
      <detEvento versaoEvento="4.00">{$det}</detEvento>
    </infEvento>
  </eventoCTe>
  <retEventoCTe versao="4.00">
    <infEvento><tpAmb>1</tpAmb><verAplic>TESTE</verAplic><cOrgao>42</cOrgao>
      <cStat>135</cStat><xMotivo>Evento registrado</xMotivo><chCTe>{$chave}</chCTe>
      <tpEvento>{$tpEvento}</tpEvento><nSeqEvento>1</nSeqEvento>
      <dhRegEvento>2027-03-21T11:00:01-03:00</dhRegEvento><nProt>442270000000555</nProt></infEvento>
  </retEventoCTe>
</procEventoCTe>
XML;
    }

    public function test_evento_cte_sem_xjust_nao_quebra_e_nao_vira_cancelamento(): void
    {
        $chave = '42270355443322000111570010000007771000000572';

        // EPEC (110113): detEvento sem xJust/xCorrecao
        $det = '<evEPECCTe><descEvento>EPEC</descEvento></evEPECCTe>';

        $this->envia('eventos/cte', $this->xmlEventoCte($chave, '110113', $det), 'epec.xml')
            ->assertOk()->assertExactJson(['msg' => '100']);

        $evento = DB::table('event_documents')->where('nfe_key', $chave)->first();
        $this->assertNotNull($evento);
        $this->assertSame('', (string) $evento->justification);
        $this->assertStringContainsString('/Evento/', (string) $evento->path_xml,
            'Evento CT-e que não é cancelamento/CCe não pode ser arquivado como "Cancelamento".');
    }
}
