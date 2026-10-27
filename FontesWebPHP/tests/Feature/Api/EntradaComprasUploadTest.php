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
 * Entrada de compras: a NF-e é do FORNECEDOR (emit) e a empresa é o
 * DESTINATÁRIO (dest).
 *
 * Trava: grava model 59 + entrada='S', com cnpj_cpf = destinatário e
 * cnpj_emit = fornecedor; reenvio regrava sem erro; o nome do arquivo é
 * irrelevante; fornecedor pessoa física não quebra; aparece na aba do painel.
 */
class EntradaComprasUploadTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ_EMPRESA = '44332211000199';
    private const CNPJ_FORNECEDOR = '11223344000155';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function chave(string $nnf): string
    {
        // cUF AAMM CNPJ-DO-FORNECEDOR mod serie nNF tpEmis cNF cDV
        return '42' . '2607' . self::CNPJ_FORNECEDOR . '55' . '001' . $nnf . '1' . '00000042' . '0';
    }

    private function xmlEntrada(string $chave, string $emitTag = '<CNPJ>' . self::CNPJ_FORNECEDOR . '</CNPJ>'): string
    {
        $empresa = self::CNPJ_EMPRESA;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe>
    <infNFe Id="NFe{$chave}" versao="4.00">
      <ide><cUF>42</cUF><cNF>00000042</cNF><natOp>VENDA</natOp><mod>55</mod>
        <serie>1</serie><nNF>555</nNF><dhEmi>2026-07-01T10:00:00-03:00</dhEmi>
        <tpNF>1</tpNF><tpAmb>1</tpAmb></ide>
      <emit>{$emitTag}<xNome>FORNECEDOR DE TESTE LTDA</xNome>
        <enderEmit><xLgr>RUA F</xLgr><nro>9</nro><xBairro>CENTRO</xBairro>
          <xMun>CHAPECO</xMun><UF>SC</UF><CEP>89800000</CEP></enderEmit><IE>987654321</IE></emit>
      <dest><CNPJ>{$empresa}</CNPJ><xNome>EMPRESA COMPRADORA LTDA</xNome>
        <enderDest><xLgr>RUA D</xLgr><nro>1</nro><xBairro>CENTRO</xBairro>
          <xMun>FLORIANOPOLIS</xMun><UF>SC</UF><CEP>88000000</CEP><fone>4899999999</fone>
        </enderDest><IE>123456789</IE></dest>
      <total><ICMSTot><vNF>1250.00</vNF></ICMSTot></total>
    </infNFe>
  </NFe>
  <protNFe versao="4.00">
    <infProt><tpAmb>1</tpAmb><chNFe>{$chave}</chNFe>
      <dhRecbto>2026-07-01T10:00:01-03:00</dhRecbto><nProt>442260000000555</nProt>
      <cStat>100</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo></infProt>
  </protNFe>
</nfeProc>
XML;
    }

    private function envia(string $xml, string $filename)
    {
        return $this->post('/api/docs/nfe/upload', [
            'key' => 'Sistema',
            'file' => UploadedFile::fake()->createWithContent($filename, $xml),
        ]);
    }

    public function test_entrada_com_sufixo_novo_grava_para_a_empresa_destinataria(): void
    {
        $chave = $this->chave('000000501');

        $this->envia($this->xmlEntrada($chave), "{$chave}-entNFe.xml")
            ->assertOk()->assertExactJson(['msg' => '100']);

        $row = DB::table('documents')->where('key', $chave)->first();
        $this->assertNotNull($row, 'A entrada deveria ter sido gravada.');
        $this->assertSame(59, (int) $row->model, 'Entrada é marcada com model 59.');
        $this->assertSame('S', (string) $row->entrada);
        $this->assertSame(self::CNPJ_EMPRESA, (string) $row->cnpj_cpf, 'Entrada pertence à empresa DESTINATÁRIA.');
        $this->assertSame(self::CNPJ_FORNECEDOR, (string) $row->cnpj_emit, 'cnpj_emit guarda o fornecedor.');

        $empresa = DB::table('companies')->where('cnpj_cpf', self::CNPJ_EMPRESA)->first();
        $this->assertNotNull($empresa, 'A empresa destinatária é auto-criada.');
        $this->assertNull(
            DB::table('companies')->where('cnpj_cpf', self::CNPJ_FORNECEDOR)->first(),
            'O FORNECEDOR não pode virar empresa do portal.'
        );
    }

    public function test_reenvio_da_mesma_chave_regrava_sem_erro(): void
    {
        $chave = $this->chave('000000502');

        $this->envia($this->xmlEntrada($chave), "{$chave}-entNFe.xml")->assertOk();
        $this->envia($this->xmlEntrada($chave), "{$chave}-entNFe.xml")
            ->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertSame(1, DB::table('documents')->where('key', $chave)->count(),
            'Reenvio não pode duplicar a entrada (dedup pela chave).');
    }

    public function test_legado_flat_sem_sufixo_tambem_entra(): void
    {
        $chave = $this->chave('000000503');

        $this->envia($this->xmlEntrada($chave), "{$chave}.xml")
            ->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertNotNull(DB::table('documents')->where('key', $chave)->first());
    }

    public function test_fornecedor_pessoa_fisica_nao_quebra(): void
    {
        $chave = $this->chave('000000504');

        $this->envia($this->xmlEntrada($chave, '<CPF>12345678909</CPF>'), "{$chave}-entNFe.xml")
            ->assertOk()->assertExactJson(['msg' => '100']);

        $row = DB::table('documents')->where('key', $chave)->first();
        $this->assertNotNull($row, 'Entrada de produtor rural (CPF) deve ser aceita.');
    }

    public function test_aba_entrada_compras_lista_a_nota(): void
    {
        $chave = $this->chave('000000505');
        $this->envia($this->xmlEntrada($chave), "{$chave}-entNFe.xml")->assertOk();

        $admin = User::factory()->create(['is_admin' => 'S']);
        $this->actingAs($admin);

        $component = Livewire::test(DocumentsIndex::class, ['type' => 'entrada'])->instance();
        $baseQuery = new \ReflectionMethod($component, 'baseQuery');
        $keys = $baseQuery->invoke($component)->pluck('key');

        $this->assertTrue($keys->contains($chave), 'A aba Entrada/Compras deveria listar a entrada.');
    }
}
