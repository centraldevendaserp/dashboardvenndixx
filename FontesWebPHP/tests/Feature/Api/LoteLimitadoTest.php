<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

/**
 * Teto de linhas nos lotes JSON dos canais do ERP: cada linha dispara operação
 * de banco, e sem limite um POST grande esgota o banco num request só. O agente
 * manda 200 por lote, então o teto não encosta no uso real.
 */
class LoteLimitadoTest extends TestCase
{
    public static function rotas(): array
    {
        return [
            'status'    => ['/api/docs/status-erp/upload'],
            'nfse'      => ['/api/docs/nfse-erp/upload'],
            'descartes' => ['/api/docs/descartes-erp/upload'],
        ];
    }

    /** @dataProvider rotas */
    public function test_lote_gigante_e_recusado(string $rota): void
    {
        $rows = array_fill(0, 5001, ['chave' => '', 'situacao' => '']);

        $this->postJson($rota, ['key' => 'Sistema', 'rows' => $rows])
            ->assertStatus(422)
            ->assertJsonPath('msg', fn ($msg) => str_contains($msg, 'maximo'));
    }

    /** @dataProvider rotas */
    public function test_lote_do_tamanho_do_agente_passa(string $rota): void
    {
        // 200 = TAM_LOTE do agente. Linhas inválidas de propósito: o que
        // interessa é que o LOTE não seja recusado pelo tamanho.
        $rows = array_fill(0, 200, ['chave' => '', 'situacao' => '']);

        $this->postJson($rota, ['key' => 'Sistema', 'rows' => $rows])
            ->assertOk()
            ->assertJsonPath('msg', '100');
    }
}
