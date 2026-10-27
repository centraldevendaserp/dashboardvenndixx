<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Documents\Index;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Botão "Limpar" da barra de filtros: ele RESTAURA o padrão da aba, não esvazia.
 * Esvaziar as datas mudaria o recorte que o usuário vê ao entrar.
 */
class LimparFiltrosTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::where('email', 'admin@gmail.com')->firstOrFail());
    }

    public function test_botao_nao_aparece_sem_filtro(): void
    {
        Livewire::test(Index::class, ['type' => 'nfse'])
            ->assertDontSee('Limpar');
    }

    public function test_botao_aparece_ao_mexer_no_periodo(): void
    {
        Livewire::test(Index::class, ['type' => 'nfse'])
            ->set('first_date', '2020-01-01')
            ->assertSee('Limpar');
    }

    public function test_botao_aparece_ao_escolher_ambiente(): void
    {
        Livewire::test(Index::class, ['type' => 'nfse'])
            ->set('ambiente', '2')
            ->assertSee('Limpar');
    }

    public function test_botao_aparece_ao_marcar_status(): void
    {
        Livewire::test(Index::class, ['type' => 'nfse'])
            ->call('toggleStatus', 'nfse_cancelada')
            ->assertSee('Limpar');
    }

    /** Numa aba de MÊS, limpar devolve o mês corrente — não deixa as datas vazias. */
    public function test_limpar_restaura_o_mes_corrente_na_aba_de_mes(): void
    {
        Livewire::test(Index::class, ['type' => 'nfse'])
            ->set('first_date', '2020-01-01')
            ->set('last_date', '2020-01-31')
            ->set('ambiente', '2')
            ->call('toggleStatus', 'nfse_cancelada')
            ->call('limparFiltros')
            ->assertSet('first_date', Carbon::now()->startOfMonth()->format('Y-m-d'))
            ->assertSet('last_date', Carbon::now()->format('Y-m-d'))
            ->assertSet('ambiente', '')
            ->assertSet('statusFilter', [])
            ->assertSet('company_filter', '')
            ->assertDontSee('Limpar');
    }

    /** "Limpar" devolve o padrão da aba, não um vazio fixo — todas abrem no mês corrente. */
    public function test_limpar_restaura_o_mes_corrente_na_aba_geral(): void
    {
        Livewire::test(Index::class, ['type' => 'cancelamentos'])
            ->set('first_date', '2020-01-01')
            ->set('last_date', '2020-01-31')
            ->call('limparFiltros')
            ->assertSet('first_date', \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d'))
            ->assertSet('last_date', \Carbon\Carbon::now()->format('Y-m-d'));
    }

    public function test_limpar_volta_para_a_primeira_pagina(): void
    {
        Livewire::test(Index::class, ['type' => 'nfce'])
            ->set('ambiente', '2')
            ->set('paginators', ['docs' => 3])
            ->call('limparFiltros')
            ->assertSet('paginators.docs', 1);
    }
}
