<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Nota ainda não autorizada (sem protocolo) na pasta de enviadas: o emissor
 * grava o XML antes de transmitir, com raiz `<NFe>`/`<MDFe>` em vez do
 * envelope `*Proc`, e esses arquivos ficam lá para sempre.
 *
 * Não são documento fiscal, então têm de ser aceitos e descartados — 422 aqui
 * viraria retry a cada 30s. Autorizada, a nota é regravada e sobe de novo.
 */
class NotaSemProtocoloTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function envia(string $rota, string $xml)
    {
        return $this->post("/api/docs/{$rota}/upload", [
            'key'  => 'Sistema',
            'file' => UploadedFile::fake()->createWithContent('doc.xml', $xml),
        ]);
    }

    /** NF-e/NFC-e sem protocolo: raiz <NFe>, tpEmis 9 (contingência off-line). */
    private function nfeSemProtocolo(string $chave): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<NFe xmlns="http://www.portalfiscal.inf.br/nfe">'
            . '<infNFe Id="NFe' . $chave . '" versao="4.00">'
            . '<ide><cUF>42</cUF><nNF>999001</nNF><serie>1</serie><mod>65</mod>'
            . '<dhEmi>2027-04-10T10:00:00-03:00</dhEmi><tpAmb>1</tpAmb><tpEmis>9</tpEmis></ide>'
            . '<emit><CNPJ>11222333000181</CNPJ><xNome>EMITENTE TESTE</xNome><IE>123</IE></emit>'
            . '<total><ICMSTot><vNF>10.00</vNF></ICMSTot></total>'
            . '</infNFe></NFe>';
    }

    private function mdfeSemProtocolo(string $chave): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<MDFe xmlns="http://www.portalfiscal.inf.br/mdfe">'
            . '<infMDFe Id="MDFe' . $chave . '" versao="3.00">'
            . '<ide><cUF>42</cUF><nMDF>999002</nMDF><serie>0</serie><mod>58</mod>'
            . '<dhEmi>2027-04-10T10:00:00-03:00</dhEmi><tpAmb>1</tpAmb><tpEmis>1</tpEmis></ide>'
            . '<emit><CNPJ>11222333000181</CNPJ><xNome>EMITENTE TESTE</xNome><IE>123</IE></emit>'
            . '</infMDFe></MDFe>';
    }

    public function test_nfe_sem_protocolo_e_aceita_e_descartada(): void
    {
        $chave = str_pad('4227041122233300018165001999001', 44, '7');
        $antes = DB::table('documents')->count();

        // msg=100 é obrigatório: 422 vira retry a cada 30s para sempre.
        $this->envia('nfenfce', $this->nfeSemProtocolo($chave))
            ->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertSame($antes, DB::table('documents')->count(),
            'Nota sem protocolo nao e documento autorizado: nao pode virar linha.');
        $this->assertNull(DB::table('documents')->where('key', $chave)->first());
    }

    public function test_mdfe_sem_protocolo_e_aceita_e_descartada(): void
    {
        $chave = str_pad('4227041122233300018158000999002', 44, '8');
        $antes = DB::table('documents')->count();

        $this->envia('mdfe', $this->mdfeSemProtocolo($chave))
            ->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertSame($antes, DB::table('documents')->count());
    }

    /**
     * Regravada com o protocolo, a nota TEM de entrar: sem isto o descarte
     * engoliria a nota boa junto.
     */
    public function test_a_mesma_nota_COM_protocolo_e_importada(): void
    {
        $chave = str_pad('4227041122233300018165001999003', 44, '9');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">'
            . substr($this->nfeSemProtocolo($chave), strpos($this->nfeSemProtocolo($chave), '<NFe'))
            . '<protNFe versao="4.00"><infProt><chNFe>' . $chave . '</chNFe>'
            . '<nProt>142270000000001</nProt><cStat>100</cStat></infProt></protNFe></nfeProc>';

        $this->envia('nfenfce', $xml)->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertNotNull(DB::table('documents')->where('key', $chave)->first(),
            'Nota autorizada (com protocolo) precisa entrar normalmente.');
    }

    /** Lixo de verdade continua sendo 422 — o descarte é só para nota sem protocolo. */
    public function test_xml_realmente_fora_do_layout_continua_422(): void
    {
        $this->envia('nfenfce', '<?xml version="1.0"?><foo><bar/></foo>')->assertStatus(422);
    }
}
