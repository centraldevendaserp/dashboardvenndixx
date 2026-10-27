<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\FiscalStatus\Index;
use App\Models\FiscalStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coluna "Tipo" da aba Status SEFAZ colorida por MODELO, com a mesma paleta dos
 * cards do dashboard (--m-nfe/--m-nfce).
 */
class FiscalStatusTipoCorTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '09617165000181';

    private function rejeicao(int $modelo, int $numero): void
    {
        FiscalStatus::create([
            'key' => '4226' . '07' . self::CNPJ . $modelo . '001'
                . str_pad((string) $numero, 9, '0', STR_PAD_LEFT) . '1' . str_pad('7', 6, '7'),
            'model' => $modelo, 'cnpj_emit' => self::CNPJ, 'series' => 1, 'number' => $numero,
            'cstat' => 204, 'category' => 'rejeitada', 'x_motivo' => 'teste',
            'source' => 'sit', 'environment_type' => '1', 'dh_recbto' => '2026-07-10 10:00:00',
        ]);
    }

    private function tela()
    {
        $this->actingAs(User::where('email', 'admin@gmail.com')->firstOrFail());

        return Livewire::test(Index::class)
            ->set('first_date', '2026-07-01')
            ->set('last_date', '2026-07-31');
    }

    public function test_nfe_e_nfce_saem_com_classes_diferentes(): void
    {
        $this->rejeicao(55, 870001);
        $this->rejeicao(65, 870002);

        $this->tela()
            ->assertSee('fs-modelo--nfe')
            ->assertSee('fs-modelo--nfce');
    }

    /** Modelo sem cor própria não pode sair sem classe (cairia no CSS de outro). */
    public function test_modelo_sem_cor_cai_no_neutro(): void
    {
        $this->rejeicao(58, 870011);   // MDF-e tem cor; 67 (CT-e OS) reaproveita a do CT-e

        $this->tela()->assertSee('fs-modelo--mdfe');
    }
}
