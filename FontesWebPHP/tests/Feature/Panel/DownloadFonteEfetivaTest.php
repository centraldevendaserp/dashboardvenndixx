<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Documents\Index;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O download por id tem de resolver a MESMA tabela que a tela está mostrando: a
 * listagem segue effectiveSource(), então usar a fonte do config entrega o XML
 * de outra nota (e a aba Descartes estoura o match).
 */
class DownloadFonteEfetivaTest extends TestCase
{
    use DatabaseTransactions;

    private string $cnpj;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::where('email', 'admin@gmail.com')->firstOrFail());
        $this->cnpj = Company::query()->value('cnpj_cpf');
    }

    /** Só "Inutilizada" na aba NF-e: a fonte efetiva é disable_documents. */
    public function test_chip_inutilizada_baixa_da_tabela_certa(): void
    {
        $id = DB::table('disable_documents')->insertGetId([
            'environment_type' => '1', 'service' => 'INUTILIZAR', 'uf' => '42', 'year' => '37',
            'cnpj' => $this->cnpj, 'model' => 55, 'series' => 1,
            'number_start' => 960001, 'number_end' => 960001,
            'event_dh' => '2037-03-01 10:00:00', 'event_status' => 102,
            'protocol_number' => '960001999', 'justification' => 'teste',
            'size' => 1, 'path_xml' => '/nao-existe.xml',
        ]);

        $tela = Livewire::test(Index::class, ['type' => 'nfe'])
            ->set('first_date', '2037-01-01')->set('last_date', '2037-12-31')
            ->call('toggleStatus', 'inutilizada');

        $this->assertSame('disables', $tela->instance()->effectiveSource());

        // Resolve a inutilização (arquivo ausente => "não existe o arquivo"),
        // e não um Document com o mesmo id.
        $tela->call('downloadDocById', $id, 'xml')->assertOk();
    }

    /** Aba Descartes: não existe XML — avisa em vez de estourar 500. */
    public function test_descartes_avisa_em_vez_de_quebrar(): void
    {
        Livewire::test(Index::class, ['type' => 'descartes'])
            ->call('downloadDocById', 1, 'xml')
            ->assertOk();
    }

    /** Só "Rejeitada" na NFC-e: fonte é o ledger, também sem XML. */
    public function test_ledger_avisa_em_vez_de_quebrar(): void
    {
        $tela = Livewire::test(Index::class, ['type' => 'nfce'])
            ->call('toggleStatus', 'rejeitada');

        $this->assertSame('rejeicoes', $tela->instance()->effectiveSource());

        $tela->call('downloadDocById', 1, 'xml')->assertOk();
    }
}
