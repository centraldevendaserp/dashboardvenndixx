<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Integridade da ingestão: `documents.key` é único e o import é atômico, então
 * reenviar a mesma nota não duplica nem perde o documento. E o status do SAT
 * só é aceito se for um cStat válido, senão 100.
 */
class DocumentIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '77665544000133';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function chave(string $nnf = '000000501'): string
    {
        return '42' . '2607' . self::CNPJ . '55' . '001' . $nnf . '1' . '00000042' . '0';
    }

    private function xmlNota(string $chave): string
    {
        $cnpj = self::CNPJ;
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe><infNFe Id="NFe{$chave}" versao="4.00">
    <ide><cUF>42</cUF><cNF>00000042</cNF><mod>55</mod><serie>1</serie><nNF>501</nNF>
      <dhEmi>2026-07-06T10:00:00-03:00</dhEmi><tpNF>1</tpNF><tpAmb>1</tpAmb></ide>
    <emit><CNPJ>{$cnpj}</CNPJ><xNome>EMPRESA INTEGRIDADE LTDA</xNome><xFant>INTEGRIDADE</xFant>
      <enderEmit><xLgr>R</xLgr><nro>1</nro><xBairro>C</xBairro><xMun>FLN</xMun><UF>SC</UF>
        <CEP>88000000</CEP><fone>4899999999</fone></enderEmit><IE>123456789</IE></emit>
    <total><ICMSTot><vNF>200.00</vNF></ICMSTot></total>
  </infNFe></NFe>
  <protNFe versao="4.00"><infProt><tpAmb>1</tpAmb><chNFe>{$chave}</chNFe>
    <dhRecbto>2026-07-06T10:00:01-03:00</dhRecbto><nProt>442260000000001</nProt>
    <cStat>100</cStat><xMotivo>Autorizado</xMotivo></infProt></protNFe>
</nfeProc>
XML;
    }

    private function enviaNota(string $chave)
    {
        return $this->post('/api/docs/nfenfce/upload', [
            'key'  => 'Sistema',
            'file' => UploadedFile::fake()->createWithContent($chave . '-nfe.xml', $this->xmlNota($chave)),
        ]);
    }

    private function xmlSat(string $chave, string $vCFe = '10.00'): string
    {
        $cnpj = self::CNPJ;
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<CFe><infCFe Id="CFe{$chave}" versao="0.08">
  <ide><cUF>42</cUF><nCFe>000123</nCFe><nserieSAT>900004567</nserieSAT><mod>59</mod>
    <dEmi>20260706</dEmi><tpAmb>1</tpAmb></ide>
  <emit><CNPJ>{$cnpj}</CNPJ><xNome>SAT TESTE LTDA</xNome><IE>111222333</IE></emit>
  <total><vCFe>{$vCFe}</vCFe></total>
</infCFe></CFe>
XML;
    }

    private function enviaSat(string $chave, array $extra = [])
    {
        return $this->post('/api/docs/sat/upload', array_merge([
            'key'  => 'Sistema',
            'file' => UploadedFile::fake()->createWithContent($chave . '-sat.xml', $this->xmlSat($chave)),
        ], $extra));
    }

    /* ----------------------------- V2-3 ----------------------------- */

    public function test_reenvio_da_mesma_nota_nao_duplica(): void
    {
        $chave = $this->chave('000000510');

        $this->enviaNota($chave)->assertOk()->assertExactJson(['msg' => '100']);
        $this->enviaNota($chave)->assertOk()->assertExactJson(['msg' => '100']); // reimport

        $this->assertSame(1, DB::table('documents')->where('key', $chave)->count(),
            'Reenviar a mesma nota (mesma chave) não pode gerar 2 documentos.');
    }

    public function test_documents_key_e_unico_por_key_cnpj(): void
    {
        // A suíte roda sobre o dump: sem a migration aplicada, o índice é
        // não-único — skip em vez de falhar.
        $cols = DB::select(
            "SELECT non_unique AS nu, column_name FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = 'documents'
                AND index_name = 'documents_key_index' ORDER BY seq_in_index"
        );
        if (empty($cols) || (int) $cols[0]->nu !== 0) {
            $this->markTestSkipped('Migration make_documents_key_unique não aplicada (rode php artisan migrate).');
        }

        $this->assertSame(0, (int) $cols[0]->nu, 'documents_key_index deveria ser ÚNICO (V2-3).');
        $this->assertGreaterThanOrEqual(2, count($cols), 'O UNIQUE deve cobrir (key, cnpj_cpf), não só key.');
    }

    public function test_saida_e_entrada_da_mesma_nota_coexistem(): void
    {
        $chave = $this->chave('000000530');

        // saída (nfe_nfce): grava key=$chave, cnpj_cpf = CNPJ do emitente
        $this->enviaNota($chave)->assertOk();

        // simula a ENTRADA da mesma nota por OUTRA empresa (mesma key, cnpj diferente)
        DB::table('documents')->insert([
            'cnpj_cpf' => '11111111000191', 'ie' => '1', 'model' => 59, 'series' => 1,
            'number' => 1, 'key' => $chave, 'month_year' => '202607', 'issue_dh' => '2026-07-06',
            'path_xml' => '/x', 'protocol' => '1', 'environment_type' => '1', 'status_xml' => '100',
            'vNF' => 1, 'entrada' => 'S',
        ]);

        // reenvia a saída — o dedup por (key, cnpj_cpf) NÃO pode apagar a entrada
        $this->enviaNota($chave)->assertOk();

        $this->assertSame(2, DB::table('documents')->where('key', $chave)->count(),
            'Saída e entrada da mesma nota (mesma key, cnpj diferente) devem coexistir.');
    }

    /* ----------------------------- V2-5 ----------------------------- */

    public function test_sat_nao_mascara_status_real_como_autorizado(): void
    {
        // Um cStat de rejeição (ex.: 573) é código fiscal válido (3 dígitos) e deve
        // ser gravado COMO É — não convertido em 100 (autorizado), que mascararia.
        $chave = $this->chave('000000520');
        $this->enviaSat($chave, ['sat_status' => '573'])->assertOk();

        $status = DB::table('documents')->where('key', $chave)->value('status_xml');
        $this->assertSame('573', (string) $status, 'Status real (573) não pode ser mascarado como 100.');
    }

    public function test_sat_status_nao_numerico_vira_autorizado(): void
    {
        // Lixo (não-numérico) não é gravado cru — cai no padrão 100 (CF-e com XML = autorizado).
        $chave = $this->chave('000000521');
        $this->enviaSat($chave, ['sat_status' => 'xyz'])->assertOk();

        $status = DB::table('documents')->where('key', $chave)->value('status_xml');
        $this->assertSame('100', (string) $status, 'sat_status não-numérico deve cair no padrão 100.');
    }

    public function test_sat_aceita_status_valido_do_cliente(): void
    {
        $chave = $this->chave('000000522');
        $this->enviaSat($chave, ['sat_status' => '101'])->assertOk(); // 101 = cancelado

        $status = DB::table('documents')->where('key', $chave)->value('status_xml');
        $this->assertSame('101', (string) $status, 'Status válido (101) do SAT deveria ser aceito.');
    }
}
