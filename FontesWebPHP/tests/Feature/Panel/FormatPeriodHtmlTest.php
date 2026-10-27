<?php

namespace Tests\Feature\Panel;

use App\Helpers\Format;
use Tests\TestCase;

/**
 * Format::periodHtml é o rótulo de período de todos os relatórios e do
 * dashboard. Com um lado só, Carbon::parse('') devolve HOJE em vez de estourar —
 * daí o cuidado para não imprimir "X até X" num intervalo aberto.
 */
class FormatPeriodHtmlTest extends TestCase
{
    public function test_sem_datas_diz_todos_os_periodos(): void
    {
        $this->assertStringContainsString('todos os períodos', Format::periodHtml(null));
        $this->assertStringContainsString('todos os períodos',
            Format::periodHtml(['first_date' => '', 'last_date' => '']));
    }

    public function test_com_as_duas_datas_diz_o_intervalo(): void
    {
        $html = Format::periodHtml(['first_date' => '2026-07-01', 'last_date' => '2026-07-31']);

        $this->assertStringContainsString('01/07/2026 até 31/07/2026', $html);
    }

    public function test_so_a_inicial_diz_a_partir_de(): void
    {
        $html = Format::periodHtml(['first_date' => '2026-07-01', 'last_date' => '']);

        $this->assertSame('<small class="text-black">a partir de 01/07/2026</small>', $html);
    }

    public function test_so_a_final_diz_ate(): void
    {
        $html = Format::periodHtml(['first_date' => null, 'last_date' => '2026-07-31']);

        // String completa de propósito: com um "contains", a volta do bug ainda
        // passaria, porque o texto errado contém o trecho esperado.
        $this->assertSame('<small class="text-black">até 31/07/2026</small>', $html);
    }
}
