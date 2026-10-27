<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Robustez da query string do relatório de Documentos: a URL é pública, então
 * filtro inválido tem de ser descartado com 200, nunca virar 500.
 */
class DocumentsReportBugsTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    public function test_ignora_data_inexistente_sem_estourar(): void
    {
        $this->actingAs($this->admin());

        // 2026-13-99 tem o FORMATO certo (a regex passa) mas não existe no
        // calendário: estourava no Carbon::parse dentro do Format::periodHtml.
        $this->get(route('panel.documents.report', ['type' => 'nfce', 'first_date' => '2026-13-99']))
            ->assertOk();
    }

    public function test_ignora_status_aninhado_sem_estourar(): void
    {
        $this->actingAs($this->admin());

        // array_intersect não compara array aninhado com as strings da whitelist
        // -> "Array to string conversion".
        $this->get(route('panel.documents.report', ['type' => 'nfce', 'status' => [['a'], ['b']]]))
            ->assertOk();
    }

    public function test_data_com_dia_invalido_e_descartada_e_nao_reinterpretada(): void
    {
        $this->actingAs($this->admin());

        // O Carbon é leniente com dia fora do mês: sem o checkdate, 30/02 vira
        // 02/03 e aplica um recorte errado em silêncio. A data tem de ser descartada.
        $this->get(route('panel.documents.report', ['type' => 'nfce', 'last_date' => '2026-02-30']))
            ->assertOk()
            ->assertSee('todos os períodos');
    }
}
