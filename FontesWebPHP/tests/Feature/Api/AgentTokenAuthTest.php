<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Autenticação do agente por token de instalação, convivendo com a chave legada.
 * Usa a rota real de upload: sem arquivo, 422 = autenticou (barrou só por falta
 * de XML) e 403 = negou.
 */
class AgentTokenAuthTest extends TestCase
{
    use DatabaseTransactions;

    private const ROUTE = '/api/docs/nfenfce/upload';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['app.system_access_key' => 'Sistema', 'app.legacy_key_enabled' => true]);
    }

    private function token(User $user, array $abilities = ['agent:upload']): string
    {
        return $user->createToken('PC Teste', $abilities)->plainTextToken;
    }

    public function test_token_valido_autentica(): void
    {
        $user = User::factory()->create();

        $this->post(self::ROUTE, ['key' => $this->token($user)])
            ->assertStatus(422); // autenticou; barrou só por falta de arquivo
    }

    public function test_token_revogado_nega(): void
    {
        $user = User::factory()->create();
        $plain = $this->token($user);
        $user->tokens()->delete(); // revoga

        $this->post(self::ROUTE, ['key' => $plain])
            ->assertStatus(403)
            ->assertJsonPath('msg', fn ($msg) => $msg !== '100' && filled($msg));
    }

    public function test_token_sem_habilidade_agent_upload_nega(): void
    {
        $user = User::factory()->create();
        $plain = $this->token($user, ['outro:escopo']);

        $this->post(self::ROUTE, ['key' => $plain])->assertStatus(403);
    }

    public function test_token_expirado_nega(): void
    {
        $user = User::factory()->create();
        $plain = $user->createToken('PC Teste', ['agent:upload'], now()->subDay())->plainTextToken;

        $this->post(self::ROUTE, ['key' => $plain])
            ->assertStatus(403)
            ->assertJsonPath('msg', fn ($msg) => $msg !== '100' && filled($msg));
    }

    public function test_chave_legada_autentica_com_convivencia_ligada(): void
    {
        $this->post(self::ROUTE, ['key' => 'Sistema'])->assertStatus(422);
    }

    public function test_chave_legada_negada_com_kill_switch(): void
    {
        config(['app.legacy_key_enabled' => false]);

        $this->post(self::ROUTE, ['key' => 'Sistema'])->assertStatus(403);
    }

    public function test_token_continua_valido_com_kill_switch(): void
    {
        config(['app.legacy_key_enabled' => false]);
        $user = User::factory()->create();

        $this->post(self::ROUTE, ['key' => $this->token($user)])->assertStatus(422);
    }

    public function test_last_used_at_e_carimbado(): void
    {
        $user = User::factory()->create();
        $plain = $this->token($user);
        $this->assertNull($user->tokens()->first()->last_used_at);

        $this->post(self::ROUTE, ['key' => $plain])->assertStatus(422);

        $this->assertNotNull(
            $user->tokens()->first()->fresh()->last_used_at,
            'O middleware deveria carimbar last_used_at no token autenticado.'
        );
    }

    public function test_upload_completo_com_token_grava_documento(): void
    {
        $user = User::factory()->create();
        $chave = '42' . '2507' . '77665544000133' . '55' . '001' . '000000901' . '1' . '00000042' . '0';

        // O token só grava nos CNPJs vinculados ao dono (AgentScope): a empresa
        // tem de estar cadastrada e vinculada antes de o agente enviar.
        $empresa = \App\Models\Company::create([
            'cnpj_cpf' => '77665544000133',
            'corporate_name' => 'EMPRESA TOKEN LTDA',
        ]);
        $user->companies()->attach($empresa->id);

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe><infNFe Id="NFe{$chave}" versao="4.00">
    <ide><cUF>42</cUF><cNF>00000042</cNF><mod>55</mod><serie>1</serie><nNF>901</nNF>
      <dhEmi>2026-07-06T10:00:00-03:00</dhEmi><tpNF>1</tpNF><tpAmb>1</tpAmb></ide>
    <emit><CNPJ>77665544000133</CNPJ><xNome>EMPRESA TOKEN LTDA</xNome><xFant>TOKEN</xFant>
      <enderEmit><xLgr>R</xLgr><nro>1</nro><xBairro>C</xBairro><xMun>FLN</xMun><UF>SC</UF>
        <CEP>88000000</CEP><fone>4899999999</fone></enderEmit><IE>123456789</IE></emit>
    <total><ICMSTot><vNF>200.00</vNF></ICMSTot></total>
  </infNFe></NFe>
  <protNFe versao="4.00"><infProt><tpAmb>1</tpAmb><chNFe>{$chave}</chNFe>
    <dhRecbto>2026-07-06T10:00:01-03:00</dhRecbto><nProt>442260000000001</nProt>
    <cStat>100</cStat><xMotivo>Autorizado</xMotivo></infProt></protNFe>
</nfeProc>
XML;

        $this->post(self::ROUTE, [
            'key'  => $this->token($user),
            'file' => UploadedFile::fake()->createWithContent($chave . '-nfe.xml', $xml),
        ])->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertSame(
            1,
            DB::table('documents')->where('key', $chave)->count(),
            'O upload autenticado por token deveria gravar o documento.'
        );
    }
}
