<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A chave do agente vale só para os CNPJs do dono dela. Sem isso a credencial
 * provaria "é um agente", não "é o agente DESTE cliente" — e a gravação
 * (delete-then-insert) sobrescreveria documento alheio.
 *
 * Duas isenções deliberadas: a chave legada e o token de admin.
 */
class EscopoDoTokenTest extends TestCase
{
    use DatabaseTransactions;

    private const MEU = '77665544000133';
    private const ALHEIO = '11223344000155';

    private function token(User $user): string
    {
        return $user->createToken('teste', ['agent:upload'])->plainTextToken;
    }

    private function empresa(string $cnpj): Company
    {
        return Company::create(['cnpj_cpf' => $cnpj, 'corporate_name' => "EMPRESA {$cnpj}"]);
    }

    private function xml(string $cnpj, string $nnf): string
    {
        $chave = '42' . '2507' . $cnpj . '55' . '001' . $nnf . '1' . '00000042' . '0';

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe><infNFe Id="NFe{$chave}" versao="4.00">
    <ide><cUF>42</cUF><cNF>00000042</cNF><mod>55</mod><serie>1</serie><nNF>{$nnf}</nNF>
      <dhEmi>2026-07-06T10:00:00-03:00</dhEmi><tpNF>1</tpNF><tpAmb>1</tpAmb></ide>
    <emit><CNPJ>{$cnpj}</CNPJ><xNome>EMPRESA {$cnpj}</xNome><xFant>X</xFant>
      <enderEmit><xLgr>R</xLgr><nro>1</nro><xBairro>C</xBairro><xMun>FLN</xMun><UF>SC</UF>
        <CEP>88000000</CEP><fone>4899999999</fone></enderEmit><IE>123456789</IE></emit>
    <total><ICMSTot><vNF>200.00</vNF></ICMSTot></total>
  </infNFe></NFe>
  <protNFe versao="4.00"><infProt><tpAmb>1</tpAmb><chNFe>{$chave}</chNFe>
    <dhRecbto>2026-07-06T10:00:01-03:00</dhRecbto><nProt>4422600{$nnf}</nProt>
    <cStat>100</cStat><xMotivo>Autorizado</xMotivo></infProt></protNFe>
</nfeProc>
XML;
    }

    private function envia(string $chaveApi, string $cnpj, string $nnf)
    {
        return $this->post('/api/docs/nfenfce/upload', [
            'key'  => $chaveApi,
            'file' => UploadedFile::fake()->createWithContent("n{$nnf}.xml", $this->xml($cnpj, $nnf)),
        ]);
    }

    /* ------------------------------ o caso ------------------------------ */

    public function test_token_nao_grava_no_cnpj_de_outro_cliente(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);
        $user->companies()->attach($this->empresa(self::MEU)->id);
        $this->empresa(self::ALHEIO);   // existe, mas não é dele

        $this->envia($this->token($user), self::ALHEIO, '000000801')->assertStatus(403);

        $this->assertSame(0, DB::table('documents')->where('cnpj_cpf', self::ALHEIO)->count());
    }

    /**
     * O estrago evitado: a gravação é delete-then-insert por (key, cnpj_cpf), e
     * sem o escopo o envio alheio apagava a linha original.
     */
    public function test_token_alheio_nao_sobrescreve_documento_existente(): void
    {
        $dono = User::factory()->create(['is_admin' => 'N']);
        $dono->companies()->attach($this->empresa(self::MEU)->id);

        $this->envia($this->token($dono), self::MEU, '000000811')->assertOk();
        $antes = DB::table('documents')->where('cnpj_cpf', self::MEU)->where('number', 811)->first();
        $this->assertNotNull($antes);

        $intruso = User::factory()->create(['is_admin' => 'N']);
        $intruso->companies()->attach($this->empresa(self::ALHEIO)->id);

        $this->envia($this->token($intruso), self::MEU, '000000811')->assertStatus(403);

        $depois = DB::table('documents')->where('cnpj_cpf', self::MEU)->where('number', 811)->first();
        $this->assertNotNull($depois, 'o documento do dono foi APAGADO pelo envio alheio');
        $this->assertSame($antes->key, $depois->key);
    }

    public function test_token_grava_no_proprio_cnpj(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);
        $user->companies()->attach($this->empresa(self::MEU)->id);

        $this->envia($this->token($user), self::MEU, '000000821')->assertOk()
            ->assertExactJson(['msg' => '100']);

        $this->assertSame(1, DB::table('documents')->where('cnpj_cpf', self::MEU)->where('number', 821)->count());
    }

    /** A recusa orienta — e nunca responde msg=100, senão o agente descartaria o XML. */
    public function test_recusa_explica_e_nao_marca_como_enviado(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);
        $user->companies()->attach($this->empresa(self::MEU)->id);

        $r = $this->envia($this->token($user), self::ALHEIO, '000000831');

        $this->assertNotSame('100', $r->json('msg'));
        $this->assertStringContainsString('Vincule a empresa', $r->json('msg'));
    }

    /* --------------------------- as isenções ---------------------------- */

    public function test_token_de_admin_nao_e_restrito(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);

        $this->envia($this->token($admin), self::ALHEIO, '000000841')->assertOk();
    }

    /** Chave legada não tem dono — segue sem restrição (motivo para migrar). */
    public function test_chave_legada_segue_sem_restricao(): void
    {
        $this->envia('Sistema', self::ALHEIO, '000000851')->assertOk();
    }
}
