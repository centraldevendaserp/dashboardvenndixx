<?php

namespace Tests\Feature\Api;

use App\Livewire\Panel\Documents\Index;
use App\Models\FiscalStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Canal de status do ERP para NF-e/NFC-e. O mapa de SITUACAO é POR MODELO (a
 * NF-e usa números e a NFC-e, letras) e a "Duplicidade" da NFC-e tem nome
 * próprio — lê-la como "denegada" gravaria status fiscal falso.
 *
 * Situação fora do mapa não vira palpite: é ignorada e logada.
 */
class ErpStatusNfeNfceTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '09617165000181';

    /** Chave de 44 dígitos com modelo/número controlados (tpEmis normal). */
    private function chave(string $modelo, string $numero): string
    {
        $base = '4226' . '07' . self::CNPJ . $modelo . '001' . str_pad($numero, 9, '0', STR_PAD_LEFT);

        return $base . '1' . str_pad('7', 44 - strlen($base) - 1, '7');
    }

    private function envia(array $rows)
    {
        return $this->postJson('/api/docs/status-erp/upload', ['key' => 'Sistema', 'rows' => $rows]);
    }

    /* ------------------------- mapa por modelo -------------------------- */

    /** O que estava inerte: número na NF-e agora vira categoria. */
    public function test_numero_da_nfe_vira_categoria(): void
    {
        $auth = $this->chave('55', '800001');
        $canc = $this->chave('55', '800002');

        $this->envia([
            ['chave' => $auth, 'situacao' => '2'],
            ['chave' => $canc, 'situacao' => '3'],
        ])->assertOk()->assertJson(['msg' => '100', 'gravados' => 2, 'ignorados' => 0]);

        $this->assertSame('autorizada', FiscalStatus::where('key', $auth)->value('category'));
        $this->assertSame('cancelada', FiscalStatus::where('key', $canc)->value('category'));
    }

    public function test_d_da_nfce_e_duplicidade_e_nao_denegada(): void
    {
        $chave = $this->chave('65', '800011');

        $this->envia([['chave' => $chave, 'situacao' => 'D']])->assertOk();

        $this->assertSame(
            FiscalStatus::CATEGORY_DUPLICIDADE,
            FiscalStatus::where('key', $chave)->value('category')
        );
    }

    /** Número não atravessa para a NFC-e: '2' é da NF-e e não quer dizer nada lá. */
    public function test_numero_nao_vale_para_nfce(): void
    {
        $chave = $this->chave('65', '800021');

        $this->envia([['chave' => $chave, 'situacao' => '2']])
            ->assertOk()->assertJson(['gravados' => 0, 'ignorados' => 1]);

        $this->assertNull(FiscalStatus::where('key', $chave)->first());
    }

    /** As letras da spec original continuam valendo nos DOIS modelos. */
    public function test_letras_continuam_valendo_na_nfe(): void
    {
        $chave = $this->chave('55', '800031');

        $this->envia([['chave' => $chave, 'situacao' => 'R']])->assertOk()->assertJson(['gravados' => 1]);

        $this->assertSame('rejeitada', FiscalStatus::where('key', $chave)->value('category'));
    }

    /* --------------------- situação sem confirmação --------------------- */

    /**
     * Situação sem significado provado tem de ser ignorada E logada como aviso —
     * é assim que ela se revela num cliente real, em vez de virar status inventado.
     */
    public function test_situacao_desconhecida_e_ignorada_com_aviso(): void
    {
        Log::spy();
        $chave = $this->chave('55', '800041');

        $this->envia([['chave' => $chave, 'situacao' => '4']])
            ->assertOk()->assertJson(['gravados' => 0, 'ignorados' => 1]);

        $this->assertNull(FiscalStatus::where('key', $chave)->first());
        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg) => str_contains($msg, 'DESCONHECIDA'))->once();
    }

    /** Nota que nunca foi transmitida é conhecida — ignorada SEM alarde. */
    public function test_gravada_e_ignorada_sem_aviso(): void
    {
        Log::spy();

        $this->envia([
            ['chave' => $this->chave('65', '800051'), 'situacao' => 'G'],
            ['chave' => $this->chave('55', '800052'), 'situacao' => '1'],
        ])->assertOk()->assertJson(['gravados' => 0, 'ignorados' => 2]);

        Log::shouldNotHaveReceived('warning');
    }

    /* ------------------- não degradar a linha do XML -------------------- */

    /**
     * O ERP só sabe a situação: confirmando o que o canal XML já dizia, o
     * detalhe (cStat, motivo, protocolo) fica, senão a tela perde o que explica
     * a rejeição.
     */
    public function test_erp_confirmando_a_categoria_preserva_o_detalhe_do_xml(): void
    {
        $chave = $this->chave('65', '800061');
        FiscalStatus::create([
            'key' => $chave, 'model' => 65, 'cnpj_emit' => self::CNPJ, 'series' => 1,
            'number' => 800061, 'cstat' => 204, 'category' => 'rejeitada',
            'x_motivo' => 'Duplicidade de NF-e', 'n_prot' => '999', 'source' => 'sit',
            'environment_type' => '2', 'dh_recbto' => '2026-07-20 10:00:00',
        ]);

        $this->envia([['chave' => $chave, 'situacao' => 'R']])->assertOk();

        $row = FiscalStatus::where('key', $chave)->firstOrFail();
        $this->assertSame(204, (int) $row->cstat);
        $this->assertSame('Duplicidade de NF-e', $row->x_motivo);
        $this->assertSame('999', $row->n_prot);
        $this->assertSame('erp', $row->source);
    }

    /**
     * Mudando a categoria, o detalhe antigo descreve um estado morto e vai
     * embora — menos o ambiente, que é da emissão.
     */
    public function test_erp_mudando_a_categoria_limpa_o_detalhe_antigo(): void
    {
        $chave = $this->chave('65', '800071');
        FiscalStatus::create([
            'key' => $chave, 'model' => 65, 'cnpj_emit' => self::CNPJ, 'series' => 1,
            'number' => 800071, 'cstat' => 204, 'category' => 'rejeitada',
            'x_motivo' => 'Duplicidade de NF-e', 'n_prot' => '999', 'source' => 'sit',
            'environment_type' => '2', 'dh_recbto' => '2026-07-20 10:00:00',
        ]);

        $this->envia([['chave' => $chave, 'situacao' => 'D']])->assertOk();

        $row = FiscalStatus::where('key', $chave)->firstOrFail();
        $this->assertSame(FiscalStatus::CATEGORY_DUPLICIDADE, $row->category);
        $this->assertNull($row->cstat);
        $this->assertNull($row->x_motivo);
        $this->assertNull($row->n_prot);
        $this->assertSame('2', $row->environment_type, 'tpAmb é da emissão, não da situação');
        $this->assertNotNull($row->dh_recbto, 'sem data nova, a antiga fica');
    }

    /* ------------------------------ na tela ------------------------------ */

    /**
     * A duplicidade não tem chip próprio: lista dentro de "Rejeitada" e o badge
     * da linha é que a nomeia. Sem isso ela ficaria invisível — não está em
     * `documents` nem no universo da aba Status SEFAZ.
     */
    public function test_duplicidade_lista_dentro_de_rejeitada_na_aba_nfce(): void
    {
        $chave = $this->chave('65', '800081');
        $this->envia([['chave' => $chave, 'situacao' => 'D', 'emissao' => '2026-07-10 09:00:00']])->assertOk();

        $this->actingAs(User::where('email', 'admin@gmail.com')->firstOrFail());

        Livewire::test(Index::class, ['type' => 'nfce'])
            ->set('first_date', '2026-07-01')
            ->set('last_date', '2026-07-31')
            ->call('toggleStatus', 'rejeitada')
            ->assertSee('800081')
            ->assertSee('Duplicidade');   // o badge preserva o nome
    }

    /** Chip de duplicidade não existe em aba nenhuma. */
    public function test_duplicidade_nao_virou_chip(): void
    {
        foreach (Index::types() as $tipo => $cfg) {
            $this->assertNotContains('duplicidade', $cfg['statuses'], "aba {$tipo}");
        }
    }

    /** Ela também soma no CONTADOR de "Rejeitada", senão o número mentiria. */
    public function test_duplicidade_soma_no_contador_de_rejeitada(): void
    {
        $q = fn () => (new \App\Support\DocumentTypeQuery('nfce', [], '', '2026-07-01', '2026-07-31'))
            ->ledgerCount('rejeitada');

        $antes = $q();
        $this->envia([['chave' => $this->chave('65', '800085'), 'situacao' => 'D', 'emissao' => '2026-07-10 09:00:00']])->assertOk();

        $this->actingAs(User::where('email', 'admin@gmail.com')->firstOrFail());
        $this->assertSame($antes + 1, $q());
    }

    /**
     * A duplicidade fica fora da aba "Status SEFAZ": o universo de lá é
     * rejeitada ∪ contingência, e a soma dos chips tem de bater com o total.
     */
    public function test_duplicidade_nao_entra_no_universo_do_status_sefaz(): void
    {
        $chave = $this->chave('65', '800091');
        $this->envia([['chave' => $chave, 'situacao' => 'D']])->assertOk();

        $this->assertSame(
            0,
            FiscalStatus::query()->rejeitadaOuContingencia()->where('key', $chave)->count()
        );
    }

    /**
     * ZIP com só chips do ledger marcados não pode estourar: recusa não devolve
     * XML de nota, então o caminho certo é avisar, não dar 500.
     */
    public function test_zip_do_ledger_avisa_em_vez_de_quebrar(): void
    {
        $this->envia([['chave' => $this->chave('65', '800111'), 'situacao' => 'D']])->assertOk();
        $this->actingAs(User::where('email', 'admin@gmail.com')->firstOrFail());

        foreach ([['rejeitada'], ['duplicidade'], ['rejeitada', 'duplicidade']] as $chips) {
            $tela = Livewire::test(Index::class, ['type' => 'nfce'])
                ->set('first_date', '2001-01-01')
                ->set('last_date', '2026-12-31');

            foreach ($chips as $chip) {
                $tela->call('toggleStatus', $chip);
            }

            $tela->call('downloadXmls')->assertOk();
        }
    }

    /** Chave que não é de nota (modelo fora do mapa) não entra. */
    public function test_modelo_fora_do_mapa_e_ignorado(): void
    {
        $chave = $this->chave('57', '800101');   // CT-e não tem NFE_MASTER/NFCE_MASTER

        $this->envia([['chave' => $chave, 'situacao' => 'T']])
            ->assertOk()->assertJson(['gravados' => 0, 'ignorados' => 1]);

        $this->assertSame(0, DB::table('fiscal_status')->where('key', $chave)->count());
    }
}
