<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ingestão de NFS-e: contrato (403/422), import dos padrões Sefin Nacional,
 * ABRASF municipal e IPM, e dedup por `identidade`.
 *
 * ⚠️ Os XMLs seguem os caminhos documentados; prefeitura nova pode exigir
 * ajuste no parser (ver DocsController::nfse).
 */
class NfseUploadTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function envia(string $xml, string $filename = 'nfse.xml', string $key = 'Sistema')
    {
        return $this->post('/api/docs/nfse/upload', [
            'key'  => $key,
            'file' => UploadedFile::fake()->createWithContent($filename, $xml),
        ]);
    }

    private function xmlNacional(string $chave, string $cnpj = '11222333000181', string $numero = '900001', string $valor = '250.00'): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<NFSe xmlns="http://www.sped.fazenda.gov.br/nfse">
  <infNFSe Id="NFS{$chave}">
    <nNFSe>{$numero}</nNFSe>
    <cLocEmi>4205407</cLocEmi>
    <dhProc>2027-03-15T10:00:00-03:00</dhProc>
    <tpAmb>1</tpAmb>
    <emit>
      <CNPJ>{$cnpj}</CNPJ>
      <xNome>PRESTADORA NACIONAL LTDA</xNome>
      <IM>12345</IM>
    </emit>
    <valores><vLiq>{$valor}</vLiq></valores>
  </infNFSe>
</NFSe>
XML;
    }

    private function xmlMunicipal(string $numero, string $cnpj = '44555666000199', string $codVer = 'ABC123XYZ', string $valor = '180.00'): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<CompNfse xmlns="http://www.abrasf.org.br/nfse.xsd">
  <Nfse>
    <InfNfse Id="nfse_{$numero}">
      <Numero>{$numero}</Numero>
      <CodigoVerificacao>{$codVer}</CodigoVerificacao>
      <DataEmissao>2027-03-15T10:00:00</DataEmissao>
      <PrestadorServico>
        <IdentificacaoPrestador>
          <CpfCnpj><Cnpj>{$cnpj}</Cnpj></CpfCnpj>
          <InscricaoMunicipal>98765</InscricaoMunicipal>
        </IdentificacaoPrestador>
        <RazaoSocial>PRESTADORA MUNICIPAL LTDA</RazaoSocial>
      </PrestadorServico>
      <OrgaoGerador><CodigoMunicipio>4205407</CodigoMunicipio></OrgaoGerador>
      <ValoresNfse><ValorLiquidoNfse>{$valor}</ValorLiquidoNfse></ValoresNfse>
      <Servico>
        <Valores><ValorServicos>200.00</ValorServicos></Valores>
        <Endereco><Numero>500</Numero></Endereco>
      </Servico>
    </InfNfse>
  </Nfse>
</CompNfse>
XML;
    }

    /* ------------------------- IPM / Atende.Net ------------------------- */

    /**
     * Retorno autorizado do IPM: raiz <nfse> minúscula, sem prolog, campos em
     * snake_case, valor com vírgula decimal e data em dd/mm/aaaa.
     */
    private function xmlIpm(
        string $numero = '5',
        string $codVer = '8233150327190014200556667772027077398197',
        string $situacao = '1',
        string $descricao = 'Emitida',
        string $valor = '1.250,90',
        string $cnpj = '55666777000188',
        bool $comChaveNacional = true
    ): string {
        // Município ainda não integrado ao padrão nacional não devolve a chave.
        $chave = $comChaveNacional
            ? '4205407' . '1' . '2' . $cnpj . str_pad($numero, 13, '0', STR_PAD_LEFT) . '2703' . '000000001' . '0'
            : '';

        return '<nfse><nf>'
            . "<numero_nfse>{$numero}</numero_nfse><serie_nfse>1</serie_nfse>"
            . '<data_nfse>15/03/2027</data_nfse><data_fato>15/03/2027</data_fato><hora_nfse>19:00:14</hora_nfse>'
            . "<situacao_codigo_nfse>{$situacao}</situacao_codigo_nfse>"
            . "<situacao_descricao_nfse>{$descricao}</situacao_descricao_nfse>"
            . "<link_nfse>https://palhoca.atende.net/detalhar/1/identificador/{$codVer}</link_nfse>"
            . "<cod_verificador_autenticidade>{$codVer}</cod_verificador_autenticidade>"
            . "<chave_acesso_nfse_nacional>{$chave}</chave_acesso_nfse_nacional>"
            . "<valor_total>{$valor}</valor_total><valor_desconto>0,00</valor_desconto>"
            . '</nf>'
            . "<prestador><cpfcnpj>{$cnpj}</cpfcnpj><cidade>8233</cidade>"
            . '<inscricao_municipal>37482</inscricao_municipal></prestador>'
            . '<tomador><tipo>J</tipo><cpfcnpj>19025417000137</cpfcnpj>'
            . '<nome_razao_social>TOMADOR TESTE LTDA</nome_razao_social></tomador>'
            . '<itens><lista><descritivo>DESENVOLVIMENTO DE SISTEMAS</descritivo>'
            . '<valor_tributavel>1.250,90</valor_tributavel></lista></itens></nfse>';
    }

    /** XML de ENVIO (RPS) que o emissor deixa na MESMA pasta Enviadas. */
    private function xmlIpmEnvio(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<nfse Id="13"><nf><data_fato_gerador>15/03/2027</data_fato_gerador>'
            . '<valor_total>150,00</valor_total><cod_verificador_autenticidade/><link_nfse/>'
            . '<numero_nfse/><serie_nfse/><data_nfse>30/12/1899</data_nfse><hora_nfse>00:00:00</hora_nfse></nf>'
            . '<prestador><cpfcnpj>55666777000188</cpfcnpj><cidade>8233</cidade></prestador>'
            . '<tomador><tipo>F</tipo><cpfcnpj>05904305990</cpfcnpj></tomador></nfse>';
    }

    /** Emissão de HOMOLOGAÇÃO: tem número e código verificador, mas nunca virou nota. */
    private function xmlIpmHomologacao(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<nfse Id="7"><nfse_teste>1</nfse_teste><nf>'
            . '<numero_nfse>2</numero_nfse><serie_nfse>1</serie_nfse><data_nfse>15/03/2027</data_nfse>'
            . '<hora_nfse>18:05:02</hora_nfse>'
            . '<cod_verificador_autenticidade>8233150327180502400556667772027077398174</cod_verificador_autenticidade>'
            . '<valor_total>665,00</valor_total></nf>'
            . '<prestador><cpfcnpj>55666777000188</cpfcnpj><cidade>8233</cidade></prestador></nfse>';
    }

    /** Recibo de CANCELAMENTO (`NFSe_<n>_canc.xml`): raiz <retorno>, SEM CNPJ do prestador. */
    private function xmlIpmCancelamento(string $numero = '5', string $codVer = '8233150327190014200556667772027077398197'): string
    {
        return '<retorno><mensagem><codigo>00001 - Sucesso</codigo></mensagem>'
            . "<numero_nfse>{$numero}</numero_nfse><serie_nfse>1</serie_nfse>"
            . '<data_nfse>16/03/2027</data_nfse><hora_nfse>03:06:27</hora_nfse>'
            . '<situacao_codigo_nfse>2</situacao_codigo_nfse>'
            . '<situacao_descricao_nfse>Cancelada</situacao_descricao_nfse>'
            . "<link_nfse>https://palhoca.atende.net/detalhar/1/identificador/{$codVer}</link_nfse>"
            . "<cod_verificador_autenticidade>{$codVer}</cod_verificador_autenticidade></retorno>";
    }

    /** Retorno só com mensagem de erro do provedor (nenhuma nota foi gerada). */
    private function xmlIpmErro(): string
    {
        return '<retorno><mensagem>'
            . '<codigo>00368 - Codigo do item da lista de servico esta preenchido incorretamente.</codigo>'
            . '<codigo>00002 - Aliquota invalida.</codigo>'
            . '</mensagem></retorno>';
    }

    /* ----------------------------- contrato ----------------------------- */

    public function test_nfse_sem_chave_da_403(): void
    {
        $this->envia($this->xmlMunicipal('700001'), 'nfse.xml', 'ERRADA')->assertStatus(403);
    }

    public function test_nfse_sem_arquivo_da_422(): void
    {
        $this->post('/api/docs/nfse/upload', ['key' => 'Sistema'])->assertStatus(422);
    }

    public function test_xml_fora_do_layout_da_422(): void
    {
        $this->envia('<?xml version="1.0"?><foo><bar/></foo>')->assertStatus(422);
    }

    /* ------------------------------ nacional ---------------------------- */

    public function test_import_nacional_grava_por_chave(): void
    {
        $chave = str_pad('3525031122233300018100001', 50, '0');

        $this->envia($this->xmlNacional($chave))->assertOk()->assertExactJson(['msg' => '100']);

        $row = DB::table('nfse_documents')->where('chave', $chave)->first();
        $this->assertNotNull($row, 'A NFS-e nacional deveria ter sido gravada.');
        $this->assertSame('nacional', $row->padrao);
        $this->assertSame('11222333000181', $row->cnpj_prestador);
        $this->assertSame($chave, $row->identidade);           // nacional: identidade = chave
        $this->assertSame('Autorizada', $row->situacao);
        $this->assertEquals(250.00, (float) $row->valor);
    }

    /* ------------------------------ municipal --------------------------- */

    public function test_import_municipal_monta_identidade_composta(): void
    {
        $this->envia($this->xmlMunicipal('800001'))->assertOk()->assertExactJson(['msg' => '100']);

        $row = DB::table('nfse_documents')->where('numero', '800001')->where('padrao', 'municipal')->first();
        $this->assertNotNull($row, 'A NFS-e municipal deveria ter sido gravada.');
        $this->assertSame('MUN:4205407|800001|ABC123XYZ|44555666000199', $row->identidade);
        $this->assertSame('44555666000199', $row->cnpj_prestador);
        // Numero é o da nota (800001), NÃO o do endereço (500).
        $this->assertSame('800001', $row->numero);
        $this->assertEquals(180.00, (float) $row->valor);   // ValorLiquidoNfse, não ValorServicos
    }

    /* -------------------------------- dedup ----------------------------- */

    public function test_reenvio_da_mesma_nfse_nao_duplica(): void
    {
        $xml = $this->xmlMunicipal('810001');

        $this->envia($xml)->assertOk();
        $this->envia($xml)->assertOk();   // reimport (o agente reenvia a cada 30s)

        $this->assertSame(1, DB::table('nfse_documents')
            ->where('identidade', 'MUN:4205407|810001|ABC123XYZ|44555666000199')->count(),
            'Reenviar a mesma NFS-e não pode duplicar (dedup por identidade).');
    }

    /* --------------------------- IPM: import ---------------------------- */

    public function test_import_ipm_grava_a_nota_autorizada(): void
    {
        $codVer = '8233150327190014200556667772027077398197';

        $this->envia($this->xmlIpm(), 'NFSe_5.xml')->assertOk()->assertExactJson(['msg' => '100']);

        $row = DB::table('nfse_documents')->where('cod_verificacao', $codVer)->first();
        $this->assertNotNull($row, 'O retorno do IPM deveria ter sido gravado.');
        $this->assertSame('ipm', $row->padrao);
        $this->assertSame('55666777000188', $row->cnpj_prestador);
        $this->assertSame('5', $row->numero);
        $this->assertSame('1', $row->serie);
        $this->assertSame('19025417000137', $row->cnpj_cpf_tomador);
        $this->assertSame('37482', $row->ie);
        $this->assertSame('IPM:' . $codVer, $row->identidade);
        // Município = IBGE (7 primeiros da chave nacional), não o código interno 8233.
        $this->assertSame('4205407', $row->municipio);
        $this->assertSame(50, strlen((string) $row->chave));
    }

    /**
     * A chave nacional não é universal: município que não aderiu ao convênio
     * manda o retorno sem ela, e exigi-la descartaria a prefeitura em silêncio.
     */
    public function test_import_ipm_de_municipio_sem_chave_nacional(): void
    {
        $codVer = '9111150327190014200556667772027077398155';

        $this->envia($this->xmlIpm('12', $codVer, '1', 'Emitida', '80,00', '55666777000188', false), 'NFSe_12.xml')
            ->assertOk()->assertExactJson(['msg' => '100']);

        $row = DB::table('nfse_documents')->where('cod_verificacao', $codVer)->first();
        $this->assertNotNull($row, 'NFS-e sem chave nacional também é nota e precisa ser importada.');
        $this->assertNull($row->chave);
        $this->assertSame('Autorizada', $row->situacao);
        // Sem chave nacional não há IBGE: cai no código do provedor.
        $this->assertSame('8233', $row->municipio);
    }

    public function test_import_ipm_converte_data_e_valor_do_formato_brasileiro(): void
    {
        $this->envia($this->xmlIpm(), 'NFSe_5.xml')->assertOk();

        // Ancorado no código verificador: `numero` se repete entre prestadores.
        $row = DB::table('nfse_documents')->where('cod_verificacao', '8233150327190014200556667772027077398197')->first();
        $this->assertSame('2027-03-15', substr((string) $row->issue_dh, 0, 10), 'data_nfse dd/mm/aaaa deveria virar Y-m-d.');
        $this->assertSame('202703', $row->month_year);
        $this->assertEquals(1250.90, (float) $row->valor, 'valor_total "1.250,90" deveria virar 1250.90.');
    }

    /**
     * O rótulo tem de bater LETRA A LETRA com Documents\Index::statusGroups():
     * o filtro da NFS-e é textual.
     */
    public function test_import_ipm_normaliza_a_situacao_para_o_vocabulario_do_portal(): void
    {
        $this->envia($this->xmlIpm(), 'NFSe_5.xml')->assertOk();
        $this->assertSame('Autorizada', DB::table('nfse_documents')
            ->where('cod_verificacao', '8233150327190014200556667772027077398197')->value('situacao'));

        $this->envia($this->xmlIpm('6', '8233150327210240390556667772027077398198', '2', 'Cancelada'), 'NFSe_6.xml')->assertOk();
        $this->assertSame('Cancelada', DB::table('nfse_documents')
            ->where('cod_verificacao', '8233150327210240390556667772027077398198')->value('situacao'));
    }

    /**
     * O provedor entrega o mesmo retorno em arquivos de nomes diferentes: só o
     * código verificador é estável.
     */
    public function test_import_ipm_deduplica_o_mesmo_retorno_com_nomes_diferentes(): void
    {
        $this->envia($this->xmlIpm(), 'NFSe_5.xml')->assertOk();
        $this->envia('<?xml version="1.0" encoding="UTF-8"?>' . $this->xmlIpm(), '51-nfse.xml')->assertOk();

        $this->assertSame(1, DB::table('nfse_documents')
            ->where('identidade', 'IPM:8233150327190014200556667772027077398197')->count(),
            'O mesmo retorno em dois arquivos não pode virar duas notas.');
    }

    /**
     * O descarte do "não é nota" é por nome da raiz, e envelope de provedor
     * varia: o parser tem de ser tentado ANTES, senão a nota some em silêncio.
     */
    public function test_abrasf_com_raiz_nfse_minuscula_ainda_e_importado(): void
    {
        $xml = str_replace(
            ['<CompNfse xmlns="http://www.abrasf.org.br/nfse.xsd">', '</CompNfse>', '<Nfse>', '</Nfse>'],
            ['<nfse>', '</nfse>', '', ''],
            $this->xmlMunicipal('960001')
        );
        $this->assertStringContainsString('<nfse>', $xml, 'O fixture precisa ter a raiz minúscula.');

        $this->envia($xml, 'nfse.xml')->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertNotNull(
            DB::table('nfse_documents')->where('numero', '960001')->where('padrao', 'municipal')->first(),
            'NFS-e ABRASF válida não pode ser descartada só por causa do nome da tag raiz.'
        );
    }

    /* ------------------- IPM: o que NÃO vira documento ------------------ */

    /**
     * O agente varre a pasta inteira e sobe o XML de envio junto: ele não é nota,
     * mas tem de responder msg=100, senão vira retry eterno.
     */
    public function test_xml_de_envio_rps_e_aceito_sem_virar_documento(): void
    {
        $antes = DB::table('nfse_documents')->count();

        $this->envia($this->xmlIpmEnvio(), '131-nfse.xml')->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertSame($antes, DB::table('nfse_documents')->count(), 'XML de envio não pode virar documento.');
    }

    public function test_emissao_de_homologacao_nao_vira_documento(): void
    {
        $antes = DB::table('nfse_documents')->count();

        $this->envia($this->xmlIpmHomologacao(), 'nfse_5.xml')->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertSame($antes, DB::table('nfse_documents')->count(), 'Emissão de homologação não é documento fiscal.');
    }

    public function test_retorno_so_com_erro_do_provedor_e_aceito(): void
    {
        $antes = DB::table('nfse_documents')->count();

        $this->envia($this->xmlIpmErro(), '13-lista-nfse-ger.xml')->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertSame($antes, DB::table('nfse_documents')->count());
    }

    /* ------------------------ IPM: cancelamento ------------------------- */

    public function test_recibo_de_cancelamento_vira_a_nota_para_cancelada(): void
    {
        $codVer = '8233150327190014200556667772027077398197';
        $this->envia($this->xmlIpm(), 'NFSe_5.xml')->assertOk();
        $this->assertSame('Autorizada', DB::table('nfse_documents')->where('cod_verificacao', $codVer)->value('situacao'));

        $this->envia($this->xmlIpmCancelamento(), 'NFSe_5_canc.xml')->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertSame('Cancelada', DB::table('nfse_documents')->where('cod_verificacao', $codVer)->value('situacao'),
            'O recibo de cancelamento deveria virar a situação da nota já importada.');
        $this->assertSame(1, DB::table('nfse_documents')->where('cod_verificacao', $codVer)->count(),
            'O cancelamento atualiza a nota — não cria um documento novo.');
    }

    /** O recibo não traz o CNPJ do prestador: sem a nota, não há o que atualizar. */
    public function test_recibo_de_cancelamento_sem_nota_importada_e_aceito(): void
    {
        $antes = DB::table('nfse_documents')->count();

        $this->envia($this->xmlIpmCancelamento('99', '8233150327999999900556667772027077390000'), 'NFSe_99_canc.xml')
            ->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertSame($antes, DB::table('nfse_documents')->count());
    }

    /* ------------------- IPM: falha do parser é BARULHENTA --------------- */

    /**
     * Os dois modos de falha do contrato:
     *  - "não é nota" (envio/RPS)   -> msg=100, descartado de propósito;
     *  - "é nota e o parser falhou" -> 422, para o agente re-tentar. Responder
     *    100 aqui perderia a nota para sempre.
     */
    public function test_nfse_ipm_que_parece_nota_mas_nao_parseia_da_422(): void
    {
        // Tem número, situação e código verificador — mas nenhum <prestador>.
        $xml = '<nfse><nf><numero_nfse>7</numero_nfse><serie_nfse>1</serie_nfse>'
            . '<data_nfse>15/03/2027</data_nfse><situacao_codigo_nfse>1</situacao_codigo_nfse>'
            . '<situacao_descricao_nfse>Emitida</situacao_descricao_nfse>'
            . '<cod_verificador_autenticidade>8233150327000000000556667772027077390077</cod_verificador_autenticidade>'
            . '<valor_total>10,00</valor_total></nf></nfse>';

        $this->envia($xml, 'NFSe_7.xml')->assertStatus(422);
    }

    /** `nfse_teste` é lido pelo VALOR: `0` é produção, não homologação. */
    public function test_nfse_teste_zero_e_producao_e_vira_documento(): void
    {
        $codVer = '8233150327190014200556667772027077398133';
        $xml = str_replace('<nfse><nf>', '<nfse><nfse_teste>0</nfse_teste><nf>', $this->xmlIpm('31', $codVer));

        $this->envia($xml, 'NFSe_31.xml')->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertNotNull(DB::table('nfse_documents')->where('cod_verificacao', $codVer)->first(),
            'nfse_teste=0 significa PRODUÇÃO — a nota não pode ser descartada.');
    }

    /* --------------------- robustez de gravação ------------------------- */

    /** Campo maior que a coluna dava QueryException -> HTTP 500 SEM `msg` -> retry eterno. */
    public function test_campo_longo_demais_nao_derruba_o_upload(): void
    {
        $xml = $this->xmlIpm('41', str_repeat('9', 200));

        $resposta = $this->envia($xml, 'NFSe_41.xml');

        $this->assertNotSame(500, $resposta->getStatusCode(), 'Overflow de coluna não pode virar 500 sem msg.');
        $this->assertSame('100', $resposta->json('msg'));
    }

    /** CNPJ entra no caminho do Storage: sem sanitizar, vira PathTraversal -> 500. */
    public function test_cnpj_com_lixo_nao_quebra_o_storage(): void
    {
        $xml = $this->xmlIpm('42', '8233150327190014200556667772027077398144', '1', 'Emitida', '10,00', '../../../../PWNED');

        $resposta = $this->envia($xml, 'NFSe_42.xml');

        $this->assertNotSame(500, $resposta->getStatusCode(), 'CNPJ malformado não pode virar 500 sem msg.');
    }

    /** O recibo do IPM não pode cancelar nota de OUTRO padrão que tenha o mesmo código. */
    public function test_cancelamento_ipm_nao_atinge_nota_de_outro_padrao(): void
    {
        $codVer = 'COL999';
        DB::table('nfse_documents')->insert([
            'padrao' => 'municipal', 'cnpj_prestador' => '99888777000111', 'numero' => '555001',
            'cod_verificacao' => $codVer, 'identidade' => 'MUN:4205407|555001|' . $codVer . '|99888777000111',
            'situacao' => 'Autorizada', 'valor' => 10, 'issue_dh' => '2027-03-15',
        ]);

        $this->envia($this->xmlIpmCancelamento('555001', $codVer), 'NFSe_555001_canc.xml')->assertOk();

        $this->assertSame('Autorizada', DB::table('nfse_documents')->where('identidade', 'MUN:4205407|555001|' . $codVer . '|99888777000111')->value('situacao'),
            'Cancelamento do IPM não pode virar nota ABRASF de outro CNPJ.');
    }

    /* ---------------------------- datas/valores ------------------------- */

    /** `5/3/2027` caía no strtotime, que lê m/d/Y: virava 3 de MAIO, silenciosamente. */
    public function test_data_br_sem_zero_a_esquerda(): void
    {
        $codVer = '8233150327190014200556667772027077398122';
        $xml = str_replace('<data_nfse>15/03/2027</data_nfse>', '<data_nfse>5/3/2027</data_nfse>', $this->xmlIpm('51', $codVer));

        $this->envia($xml, 'NFSe_51.xml')->assertOk();

        $this->assertSame('2027-03-05', substr((string) DB::table('nfse_documents')->where('cod_verificacao', $codVer)->value('issue_dh'), 0, 10));
    }

    /** O helper de valor BR ficou só no IPM: ABRASF/Nacional liam "1.250,90" como 1.25. */
    public function test_valor_brasileiro_tambem_no_parser_abrasf(): void
    {
        $this->envia($this->xmlMunicipal('970001', '44555666000199', 'VBR123', '1.250,90'))->assertOk();

        $this->assertEquals(1250.90, (float) DB::table('nfse_documents')
            ->where('identidade', 'MUN:4205407|970001|VBR123|44555666000199')->value('valor'));
    }

    /* ---------------------------- encoding ------------------------------ */

    /**
     * Retorno sem prolog em bytes ANSI: o libxml assumiria UTF-8 e abortaria no
     * primeiro acento.
     */
    public function test_retorno_em_ansi_sem_prolog_e_importado(): void
    {
        $xml = str_replace('DESENVOLVIMENTO DE SISTEMAS', 'MANUTENÇÃO DE SOFTWARE', $this->xmlIpm());
        $ansi = mb_convert_encoding($xml, 'Windows-1252', 'UTF-8');
        $this->assertNotSame($xml, $ansi, 'O fixture precisa ter acento para o teste valer.');

        $this->envia($ansi, 'NFSe_5.xml')->assertOk()->assertExactJson(['msg' => '100']);

        $this->assertNotNull(DB::table('nfse_documents')
            ->where('cod_verificacao', '8233150327190014200556667772027077398197')->first());
    }
}
