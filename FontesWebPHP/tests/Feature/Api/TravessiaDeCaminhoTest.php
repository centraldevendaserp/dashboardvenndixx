<?php

namespace Tests\Feature\Api;

use App\Support\StoragePath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * O caminho de arquivamento do XML vem do próprio XML enviado, então quem manda
 * o arquivo escolheria o destino: um `<CNPJ>../public</CNPJ>` grava em
 * `storage/app/public`, que é servido na web pelo `storage:link`.
 *
 * ⚠️ O `..` nem precisa subir acima da raiz — o Flysystem só barra isso. O
 * estrago é sair de `docs/` e pousar noutra subpasta de `storage/app`.
 */
class TravessiaDeCaminhoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /* ------------------------- o saneador em si ------------------------- */

    public static function valoresPerigosos(): array
    {
        return [
            'sobe um nivel'      => ['../public', 'public'],
            'sobe varios'        => ['../../../../etc', 'etc'],
            'barra no meio'      => ['123/../public', '123public'],
            'contrabarra'        => ['..\\..\\windows', 'windows'],
            'so pontos'          => ['..', '_'],
            'ponto simples'      => ['.', '_'],
            'vazio'              => ['', '_'],
            'nul byte'           => ["12345\0/etc", '12345etc'],
            'quatro pontos'      => ['....', '_'],
            'cnpj normal'        => ['09617165000181', '09617165000181'],
        ];
    }

    /** @dataProvider valoresPerigosos */
    public function test_segmento_nao_muda_de_diretorio(string $entrada, string $esperado): void
    {
        $limpo = StoragePath::segmento($entrada);

        $this->assertSame($esperado, $limpo);
        $this->assertStringNotContainsString('/', $limpo);
        $this->assertStringNotContainsString('\\', $limpo);
        $this->assertStringNotContainsString('..', $limpo);
    }

    public function test_montar_mantem_o_prefixo_fixo(): void
    {
        $this->assertSame(
            '/docs/public/123/55/202607/x.xml',
            StoragePath::montar('/docs', '../public', '123', '55', '202607', 'x.xml')
        );
    }

    /** O nome do arquivo é do cliente: `basename` antes de tudo. */
    public function test_arquivo_nao_carrega_diretorio(): void
    {
        $this->assertSame('shell.php', StoragePath::arquivo('../../shell.php'));
        $this->assertSame('nota.xml', StoragePath::arquivo('/etc/nota.xml'));
    }

    /* ---------------------- o caminho real do upload --------------------- */

    private function xmlComCnpj(string $cnpj, string $numero): string
    {
        $chave = '4226079988776600015555001000' . $numero . '1' . '00000042' . '0';

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe><infNFe versao="4.00" Id="NFe{$chave}">
    <ide><cUF>42</cUF><natOp>VENDA</natOp><mod>55</mod><serie>1</serie>
      <nNF>{$numero}</nNF><dhEmi>2026-07-06T10:00:00-03:00</dhEmi><tpNF>1</tpNF>
      <tpAmb>1</tpAmb><tpEmis>1</tpEmis></ide>
    <emit><CNPJ>{$cnpj}</CNPJ><xNome>TESTE TRAVESSIA</xNome>
      <enderEmit><xLgr>R</xLgr><nro>1</nro><xBairro>C</xBairro><cMun>4204202</cMun>
        <xMun>T</xMun><UF>SC</UF><CEP>88000000</CEP></enderEmit>
      <IE>123</IE></emit>
    <total><ICMSTot><vNF>1.00</vNF></ICMSTot></total>
  </infNFe></NFe>
  <protNFe versao="4.00"><infProt><tpAmb>1</tpAmb><chNFe>{$chave}</chNFe>
    <dhRecbto>2026-07-06T10:00:05-03:00</dhRecbto><nProt>1422600{$numero}</nProt>
    <cStat>100</cStat><xMotivo>Autorizado</xMotivo></infProt></protNFe>
</nfeProc>
XML;
    }

    /** `../public` no CNPJ tem de continuar dentro de `docs/`. */
    public function test_cnpj_com_travessia_nao_escapa_de_docs(): void
    {
        $this->post('/api/docs/nfenfce/upload', [
            'key'  => 'Sistema',
            'file' => UploadedFile::fake()->createWithContent(
                'trav.xml', $this->xmlComCnpj('../public', '000990101')
            ),
        ])->assertJson(['msg' => '100'], false);

        foreach (Storage::disk('local')->allFiles() as $arquivo) {
            $this->assertStringStartsWith('docs/', $arquivo, "arquivo escapou de docs/: {$arquivo}");
            $this->assertStringNotContainsString('..', $arquivo);
        }

        $this->assertEmpty(
            Storage::disk('local')->allFiles('public'),
            'nada pode cair em storage/app/public — é a pasta publicada na web'
        );
    }

    /** O caminho legítimo continua funcionando, no lugar de sempre. */
    public function test_cnpj_normal_arquiva_onde_deve(): void
    {
        $this->post('/api/docs/nfenfce/upload', [
            'key'  => 'Sistema',
            'file' => UploadedFile::fake()->createWithContent(
                'ok.xml', $this->xmlComCnpj('99887766000155', '000990111')
            ),
        ])->assertJson(['msg' => '100'], false);

        $this->assertNotEmpty(
            Storage::disk('local')->allFiles('docs/99887766000155'),
            'o XML legítimo deveria estar em docs/<cnpj>/...'
        );
    }
}
