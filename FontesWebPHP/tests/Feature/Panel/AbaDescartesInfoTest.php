<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Documents\Index;
use App\Livewire\Panel\FiscalStatus\Index as FiscalStatusIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Três regras de leitura das abas:
 *  1. todas abrem no mês corrente, senão a mesma tela responde a perguntas de
 *     períodos diferentes conforme a aba;
 *  2. "Descartes do sistema" explica NA TELA o que é, porque o ERP chama de
 *     "Inutilizada" tanto isto quanto o evento fiscal de faixa;
 *  3. "Status SEFAZ" abre em produção — a aba mostra pendência, e rejeição de
 *     homologação é teste.
 */
class AbaDescartesInfoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::where('email', 'admin@gmail.com')->firstOrFail());
    }

    public function test_todas_as_abas_abrem_no_mes_corrente(): void
    {
        foreach (Index::types() as $tipo => $cfg) {
            $this->assertSame('month', $cfg['period'], "aba {$tipo}");
        }
    }

    /**
     * A ordem de types() É a ordem da sidebar (o menu itera o array) e tem de
     * bater com as abas do agente.
     */
    public function test_ordem_da_sidebar(): void
    {
        $this->assertSame(
            ['nfce', 'nfe', 'nfse', 'mdfe', 'cte', 'entrada', 'cancelamentos', 'inutilizacoes', 'descartes'],
            array_keys(Index::types())
        );
    }

    public function test_aba_de_descartes_explica_o_que_e(): void
    {
        Livewire::test(Index::class, ['type' => 'descartes'])
            ->assertSee('jogadas fora antes de virar nota fiscal')
            ->assertSee('Inutilizações gerais');
    }

    /** A explicação é SÓ dessa aba — nas outras seria ruído. */
    public function test_as_outras_abas_nao_mostram_a_explicacao(): void
    {
        foreach (['nfe', 'inutilizacoes', 'cancelamentos'] as $tipo) {
            Livewire::test(Index::class, ['type' => $tipo])
                ->assertDontSee('jogadas fora antes de virar nota fiscal');
        }
    }

    public function test_status_sefaz_abre_em_producao(): void
    {
        Livewire::test(FiscalStatusIndex::class)->assertSet('ambiente', '1');
    }
}
