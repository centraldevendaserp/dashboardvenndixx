<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * NFS-e pelo banco do ERP (NFSE_MASTER.SITUACAO), o canal que corrige o que o
 * XML do provedor erra: há nota cancelada no ERP cujo arquivo ainda diz
 * "Emitida", e nota que sequer tem arquivo na pasta.
 */
class ErpNfseUploadTest extends TestCase
{
    use DatabaseTransactions;

    private const COD_6 = '8233230726210240390096171652026077398221';
    private const COD_10 = '8233270726150000000096171652026077398230';

    private function envia(array $rows, string $key = 'Sistema')
    {
        return $this->postJson('/api/docs/nfse-erp/upload', ['key' => $key, 'rows' => $rows]);
    }

    private function linha(array $extra = []): array
    {
        return array_merge([
            'cnpj_prestador'  => '09617165000181',
            'numero'          => '6',
            'serie'           => '1',
            'cod_verificacao' => self::COD_6,
            'situacao'        => '3',
            'emissao'         => '2026-07-23',
            'valor'           => 15.00,
        ], $extra);
    }

    /** Grava uma NFS-e como se tivesse vindo do XML do provedor. */
    private function notaDoXml(string $codVerificacao, string $situacao, string $numero = '6'): void
    {
        DB::table('nfse_documents')->insert([
            'padrao' => 'ipm', 'cnpj_prestador' => '09617165000181', 'numero' => $numero,
            'serie' => '1', 'cod_verificacao' => $codVerificacao, 'identidade' => 'IPM:' . $codVerificacao,
            'situacao' => $situacao, 'valor' => 15.00, 'issue_dh' => '2026-07-23',
            'month_year' => '202607', 'path_xml' => '/docs/nfse/x.xml',
        ]);
    }

    /* ----------------------------- contrato ----------------------------- */

    public function test_sem_chave_da_403(): void
    {
        $this->envia([$this->linha()], 'ERRADA')->assertStatus(403);
    }

    public function test_sem_linhas_da_422(): void
    {
        $this->postJson('/api/docs/nfse-erp/upload', ['key' => 'Sistema'])->assertStatus(422);
    }

    /* --------------------- corrige o que o XML errou -------------------- */

    /** O caso que motivou o canal: nota 6, "Emitida" no XML, cancelada no ERP. */
    public function test_situacao_do_erp_corrige_a_nota_importada_do_xml(): void
    {
        $this->notaDoXml(self::COD_6, 'Autorizada');

        $this->envia([$this->linha()])->assertOk()->assertJson(['msg' => '100', 'atualizados' => 1]);

        $row = DB::table('nfse_documents')->where('cod_verificacao', self::COD_6)->first();
        $this->assertSame('Cancelada', $row->situacao);
        $this->assertSame('erp', $row->situacao_source);
        // Atualiza a linha existente, não cria outra.
        $this->assertSame(1, DB::table('nfse_documents')->where('cod_verificacao', self::COD_6)->count());
    }

    /** ERP vence o XML: reimportar o arquivo desatualizado não pode "descancelar". */
    public function test_reimport_do_xml_nao_rebaixa_a_situacao_vinda_do_erp(): void
    {
        $this->notaDoXml(self::COD_6, 'Autorizada');
        $this->envia([$this->linha()])->assertOk();

        // O agente reenvia o MESMO XML antigo (que ainda diz "Emitida").
        $xml = '<nfse><nf><numero_nfse>6</numero_nfse><serie_nfse>1</serie_nfse>'
            . '<data_nfse>23/07/2026</data_nfse><situacao_codigo_nfse>1</situacao_codigo_nfse>'
            . '<situacao_descricao_nfse>Emitida</situacao_descricao_nfse>'
            . '<cod_verificador_autenticidade>' . self::COD_6 . '</cod_verificador_autenticidade>'
            . '<valor_total>15,00</valor_total></nf>'
            . '<prestador><cpfcnpj>09617165000181</cpfcnpj><cidade>8233</cidade></prestador></nfse>';

        $this->post('/api/docs/nfse/upload', [
            'key'  => 'Sistema',
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('NFSe_6.xml', $xml),
        ])->assertOk();

        $row = DB::table('nfse_documents')->where('cod_verificacao', self::COD_6)->first();
        $this->assertSame('Cancelada', $row->situacao, 'O XML desatualizado nao pode descancelar a nota.');
        // ...mas o resto da linha continua sendo enriquecido pelo XML.
        $this->assertNotNull($row->path_xml);
    }

    /* ------------------- nota que nunca teve arquivo -------------------- */

    public function test_nota_ausente_no_disco_e_criada_a_partir_do_erp(): void
    {
        $this->envia([$this->linha([
            'numero' => '10', 'cod_verificacao' => self::COD_10, 'situacao' => '2',
            'emissao' => '2026-07-27', 'valor' => 30.00,
            'chave' => '42119001209617165000181000000000001026070000000019',
        ])])->assertOk()->assertJson(['msg' => '100', 'criados' => 1]);

        $row = DB::table('nfse_documents')->where('cod_verificacao', self::COD_10)->first();
        $this->assertNotNull($row, 'Nota sem XML na pasta ainda precisa aparecer para o contador.');
        $this->assertSame('Autorizada', $row->situacao);
        $this->assertSame('10', $row->numero);
        $this->assertSame('4211900', $row->municipio, 'municipio vem do IBGE da chave nacional.');
        $this->assertEquals(30.00, (float) $row->valor);
        $this->assertNull($row->path_xml, 'Sem XML, o download fica indisponivel so para essa nota.');
    }

    /* --------------------- emissões de homologação ---------------------- */

    /**
     * Homologação não gasta número, e o ERP sobrescreve o código de verificação
     * dela com o da emissão seguinte. Só o PROTOCOLO é único por emissão — sem
     * ele, linhas distintas colapsariam numa só.
     */
    public function test_emissoes_de_homologacao_com_numero_repetido_aparecem_todas(): void
    {
        // Valores ficticios de propósito: ancorar em código real do banco de dev
        // faria o teste passar/falhar conforme o que já foi importado por fora.
        $codFinal = '9911150327000000000556667772027099900005';   // emissao definitiva
        $tentativas = [
            '9911150327000000000556667772027099900001',
            '9911150327000000000556667772027099900002',
            '9911150327000000000556667772027099900003',
            '9911150327000000000556667772027099900004',
        ];

        $rows = [];
        foreach ($tentativas as $i => $protocolo) {
            // Todas com numero '2' e o MESMO cod_verificacao (como o ERP grava).
            $rows[] = $this->linha([
                'numero' => '2', 'cod_verificacao' => $codFinal, 'protocolo' => $protocolo,
                'situacao' => '3', 'valor' => 100 + $i,
            ]);
        }
        // A definitiva: protocolo == cod_verificacao.
        $rows[] = $this->linha([
            'numero' => '2', 'cod_verificacao' => $codFinal, 'protocolo' => $codFinal,
            'situacao' => '3', 'valor' => 30.00,
        ]);

        $this->envia($rows)->assertOk()->assertJson(['msg' => '100', 'criados' => 5]);

        $linhas = DB::table('nfse_documents')->where('numero', '2')
            ->whereIn('cod_verificacao', array_merge($tentativas, [$codFinal]))->get();
        $this->assertCount(5, $linhas, 'As 4 tentativas de homologacao + a definitiva sao 5 linhas.');

        // As 4 tentativas ficam marcadas como homologação; a definitiva, produção.
        $this->assertSame(4, $linhas->where('environment_type', '2')->count());
        $this->assertSame(1, $linhas->where('environment_type', '1')->count());
    }

    /** Na emissão de produção o protocolo É o código — os dois canais convergem. */
    public function test_protocolo_igual_ao_codigo_converge_com_a_linha_do_xml(): void
    {
        $this->notaDoXml(self::COD_6, 'Autorizada');

        $this->envia([$this->linha(['protocolo' => self::COD_6])])
            ->assertOk()->assertJson(['atualizados' => 1, 'criados' => 0]);

        $this->assertSame(1, DB::table('nfse_documents')->where('cod_verificacao', self::COD_6)->count(),
            'Protocolo == codigo nao pode criar uma segunda linha ao lado da do XML.');
    }

    /* ---------------------------- validação ----------------------------- */

    /**
     * O domínio de SITUACAO aqui é NUMÉRICO — os outros canais do ERP usam
     * letras. Situação fora do mapa é ignorada, nunca chutada.
     */
    public function test_situacao_desconhecida_e_ignorada_sem_derrubar_o_lote(): void
    {
        $resposta = $this->envia([
            $this->linha(['situacao' => 'T']),                                  // letra: dominio de outra tabela
            $this->linha(['situacao' => '9']),                                  // numero fora do mapa
            $this->linha(['cod_verificacao' => '', 'situacao' => '3']),         // sem vinculo
            $this->linha(['numero' => '11', 'cod_verificacao' => 'COD-OK-11']), // valida
        ]);

        $resposta->assertOk()->assertJson(['msg' => '100', 'ignorados' => 3, 'criados' => 1]);
        $this->assertNotNull(DB::table('nfse_documents')->where('cod_verificacao', 'COD-OK-11')->first());
    }

    public function test_sem_cnpj_do_prestador_nao_cria_nota(): void
    {
        $this->envia([$this->linha(['cnpj_prestador' => '', 'cod_verificacao' => 'SEM-CNPJ'])])
            ->assertOk()->assertJson(['ignorados' => 1]);

        $this->assertNull(DB::table('nfse_documents')->where('cod_verificacao', 'SEM-CNPJ')->first());
    }

    /** Situação já existente + reenvio do lote não pode duplicar. */
    public function test_reenvio_do_lote_e_idempotente(): void
    {
        $this->envia([$this->linha()])->assertOk();
        $this->envia([$this->linha()])->assertOk();

        $this->assertSame(1, DB::table('nfse_documents')->where('cod_verificacao', self::COD_6)->count());
    }
}
