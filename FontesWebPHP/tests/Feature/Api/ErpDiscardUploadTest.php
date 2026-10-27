<?php

namespace Tests\Feature\Api;

use App\Livewire\Panel\Documents\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Notas descartadas no sistema de vendas, vindas do banco do ERP: a venda
 * ganhou número e foi jogada fora antes de virar documento fiscal. Sem XML e
 * sem protocolo, por isso tabela e aba próprias.
 *
 * O ERP chama isto de "Inutilizada", o mesmo nome do evento fiscal de faixa —
 * confundir os dois é o que faz os totais divergirem.
 */
class ErpDiscardUploadTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '09617165000181';

    private function envia(array $rows, string $key = 'Sistema')
    {
        return $this->postJson('/api/docs/descartes-erp/upload', ['key' => $key, 'rows' => $rows]);
    }

    private function linha(array $extra = []): array
    {
        return array_merge([
            'cnpj_cpf' => self::CNPJ, 'model' => 55, 'number' => '700101',
            'series' => '1', 'situacao' => '5', 'emissao' => '2027-09-10',
            'valor' => 123.45, 'key' => '',
        ], $extra);
    }

    /* ----------------------------- contrato ----------------------------- */

    public function test_sem_chave_da_403(): void
    {
        $this->envia([$this->linha()], 'ERRADA')->assertStatus(403);
    }

    public function test_sem_linhas_da_422(): void
    {
        $this->postJson('/api/docs/descartes-erp/upload', ['key' => 'Sistema'])->assertStatus(422);
    }

    /* ------------------------------ import ------------------------------ */

    public function test_grava_descarte_de_nfe(): void
    {
        $this->envia([$this->linha()])->assertOk()->assertJson(['msg' => '100', 'gravados' => 1]);

        $row = DB::table('discarded_documents')->where('number', 700101)->first();
        $this->assertNotNull($row);
        $this->assertSame(55, (int) $row->model);
        $this->assertSame('5', $row->situacao_erp);
        $this->assertEquals(123.45, (float) $row->value);
        $this->assertSame('202709', $row->month_year);
    }

    /** A NFC-e descartada traz o TEXTO "INUTILIZADA" no lugar da chave. */
    public function test_chave_invalida_da_nfce_vira_null(): void
    {
        $this->envia([$this->linha([
            'model' => 65, 'situacao' => 'I', 'number' => '700102', 'key' => 'INUTILIZADA',
        ])])->assertOk();

        $this->assertNull(DB::table('discarded_documents')->where('number', 700102)->value('key'));
    }

    public function test_chave_de_44_digitos_e_mantida(): void
    {
        $chave = str_pad('4227090961716500018155001000000700103', 44, '7');
        $this->envia([$this->linha(['number' => '700103', 'key' => $chave])])->assertOk();

        $this->assertSame($chave, DB::table('discarded_documents')->where('number', 700103)->value('key'));
    }

    /* ---------------------------- validação ----------------------------- */

    /**
     * O mapa é POR MODELO: '5' só na NF-e, 'I' só na NFC-e. Fora disso, ignora —
     * marcar como descarte uma nota autorizada é pior que não mostrar nada.
     */
    public function test_situacao_fora_do_mapa_e_ignorada(): void
    {
        $resposta = $this->envia([
            $this->linha(['number' => '700201', 'situacao' => '2']),               // autorizada
            $this->linha(['number' => '700202', 'model' => 65, 'situacao' => 'T']), // autorizada
            $this->linha(['number' => '700203', 'model' => 65, 'situacao' => '5']), // '5' nao vale p/ NFC-e
            $this->linha(['number' => '700204', 'model' => 55, 'situacao' => 'I']), // 'I' nao vale p/ NF-e
            $this->linha(['number' => '700205']),                                   // valida
        ]);

        $resposta->assertOk()->assertJson(['msg' => '100', 'gravados' => 1, 'ignorados' => 4]);
        $this->assertSame(1, DB::table('discarded_documents')->whereIn('number', [700201, 700202, 700203, 700204, 700205])->count());
    }

    public function test_modelo_desconhecido_e_ignorado(): void
    {
        $this->envia([$this->linha(['model' => 57, 'number' => '700301'])])
            ->assertOk()->assertJson(['ignorados' => 1]);

        $this->assertNull(DB::table('discarded_documents')->where('number', 700301)->first());
    }

    public function test_reenvio_do_lote_nao_duplica(): void
    {
        $this->envia([$this->linha(['number' => '700401'])])->assertOk();
        $this->envia([$this->linha(['number' => '700401', 'valor' => 999.99])])->assertOk();

        $linhas = DB::table('discarded_documents')->where('number', 700401)->get();
        $this->assertCount(1, $linhas, 'mesma nota nao pode virar duas linhas');
        $this->assertEquals(999.99, (float) $linhas->first()->value, 'o reenvio atualiza o valor');
    }

    /* ------------------------------- tela ------------------------------- */

    public function test_aba_lista_os_descartes(): void
    {
        $this->envia([$this->linha(['number' => '700501', 'valor' => 88.80])])->assertOk();
        $this->actingAs(User::where('email', 'admin@gmail.com')->firstOrFail());

        // A aba abre no mês corrente; a nota do fixture é de 2027.
        Livewire::test(Index::class, ['type' => 'descartes'])
            ->set('first_date', '2027-01-01')
            ->set('last_date', '2027-12-31')
            ->assertSee('700501')
            ->assertSee('88,80');
    }

    /** Não há XML para baixar: o botão não pode estourar UnhandledMatchError. */
    public function test_download_de_xml_na_aba_de_descartes_nao_quebra(): void
    {
        $this->actingAs(User::where('email', 'admin@gmail.com')->firstOrFail());

        Livewire::test(Index::class, ['type' => 'descartes'])
            ->call('downloadXmls')
            ->assertOk();
    }
}
