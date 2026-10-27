<?php

namespace Tests\Feature\Api;

use App\Models\FiscalStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Ledger `fiscal_status`: mapa cStat -> categoria e persistência com casts.
 * A tabela vem de migration idempotente — rode `artisan migrate` antes da suíte.
 */
class FiscalStatusModelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_category_for_mapeia_todos_os_grupos(): void
    {
        $esperado = [
            100 => 'autorizada', 150 => 'autorizada',
            101 => 'cancelada', 135 => 'cancelada', 151 => 'cancelada', 155 => 'cancelada',
            110 => 'denegada', 205 => 'denegada', 301 => 'denegada', 302 => 'denegada', 303 => 'denegada',
            102 => 'inutilizada',
            // catch-all: rejeições comuns + limites da faixa
            217 => 'rejeitada', 613 => 'rejeitada', 704 => 'rejeitada', 976 => 'rejeitada', 999 => 'rejeitada',
        ];

        foreach ($esperado as $cstat => $categoria) {
            $this->assertSame($categoria, FiscalStatus::categoryFor($cstat), "cStat {$cstat}");
        }
    }

    public function test_cria_le_e_casta_registro(): void
    {
        $key = '42260799887766000155650010001234561000000420';

        FiscalStatus::create([
            'key' => $key, 'model' => 65, 'cnpj_emit' => '99887766000155',
            'series' => 1, 'number' => 123456, 'cstat' => 704,
            'category' => 'rejeitada', 'x_motivo' => 'Rejeicao: teste',
            'n_prot' => null, 'dh_recbto' => '2026-06-29 23:30:00',
            'environment_type' => '2', 'source' => 'pro-lot',
        ]);

        $row = FiscalStatus::where('key', $key)->firstOrFail();
        $this->assertSame('rejeitada', $row->category);
        $this->assertInstanceOf(Carbon::class, $row->dh_recbto);
    }

    /** Monta chave: cUF(2) AAMM(4) CNPJ(14) mod(2) serie(3) nNF(9) tpEmis(1) cNF(8) cDV(1). */
    private function chave(string $tpEmis, string $model = '65', string $cnpj = '99887766000155'): string
    {
        return '42' . '2607' . $cnpj . $model . '001' . '000123456' . $tpEmis . '00000042' . '0';
    }

    public function test_tp_emis_sai_da_chave(): void
    {
        $row = new FiscalStatus(['key' => $this->chave('9')]);

        $this->assertSame('9', $row->tp_emis);
        $this->assertSame(44, strlen($this->chave('9')), 'chave de teste malformada');
    }

    public function test_is_contingencia_so_para_tp_emis_diferente_de_1(): void
    {
        $normal = new FiscalStatus(['key' => $this->chave('1')]);
        $offline = new FiscalStatus(['key' => $this->chave('9')]);
        $fsda = new FiscalStatus(['key' => $this->chave('5')]);

        $this->assertFalse($normal->isContingencia(), 'tpEmis 1 e emissao normal');
        $this->assertTrue($offline->isContingencia(), 'tpEmis 9 e contingencia offline');
        $this->assertTrue($fsda->isContingencia(), 'tpEmis 5 e contingencia FS-DA');
    }

    public function test_contingencia_label_depende_do_modelo(): void
    {
        // o MESMO tpEmis=2 e "FS-IA" na NF-e e "Contingencia" no MDF-e
        $nfe = new FiscalStatus(['key' => $this->chave('2', '55'), 'model' => 55]);
        $mdfe = new FiscalStatus(['key' => $this->chave('2', '58'), 'model' => 58]);
        $nfce = new FiscalStatus(['key' => $this->chave('9', '65'), 'model' => 65]);
        $normal = new FiscalStatus(['key' => $this->chave('1', '55'), 'model' => 55]);

        $this->assertSame('FS-IA', $nfe->contingenciaLabel());
        $this->assertSame('Contingência', $mdfe->contingenciaLabel());
        $this->assertSame('Offline', $nfce->contingenciaLabel());
        $this->assertNull($normal->contingenciaLabel(), 'emissao normal nao tem rotulo');
    }

    public function test_contingencia_label_cai_no_fallback_para_codigo_desconhecido(): void
    {
        $exotico = new FiscalStatus(['key' => $this->chave('6', '58'), 'model' => 58]);

        $this->assertSame('tpEmis 6', $exotico->contingenciaLabel());
    }

    /**
     * Contingência = emitida em contingência E a SEFAZ não tem a nota (217).
     * As fixtures cobrem as três armadilhas: só o par conta.
     */
    public function test_contingencia_e_217_em_emissao_de_contingencia(): void
    {
        $cnpj = '31415926000153';
        $linhas = [
            ['nnf' => 987651001, 'tp' => '9', 'cstat' => 217],  // ENTRA: offline E a SEFAZ não tem
            ['nnf' => 987651002, 'tp' => '1', 'cstat' => 217],  // fora: emissão NORMAL (não é contingência)
            ['nnf' => 987651003, 'tp' => '9', 'cstat' => 704],  // fora: a SEFAZ RECEBEU e recusou
            ['nnf' => 987651004, 'tp' => '9', 'cstat' => 100],  // fora: a SEFAZ recebeu e autorizou
        ];
        foreach ($linhas as $l) {
            FiscalStatus::create([
                'key' => '42' . '2607' . $cnpj . '65' . '001' . $l['nnf'] . $l['tp'] . '00000042' . '0',
                'model' => 65, 'cnpj_emit' => $cnpj, 'series' => 1, 'number' => $l['nnf'],
                'cstat' => $l['cstat'], 'category' => FiscalStatus::categoryFor($l['cstat']),
                'dh_recbto' => now(), 'environment_type' => '2', 'source' => 'sit',
            ]);
        }

        $achadas = FiscalStatus::query()->where('cnpj_emit', $cnpj)
            ->emContingencia()->orderBy('number')->pluck('number');

        $this->assertSame([987651001], $achadas->all());
    }

    /** Rejeitada = recusa REAL. A nota em contingência (217) não é recusa: a SEFAZ nunca a teve. */
    public function test_rejeitada_exclui_a_contingencia(): void
    {
        $cnpj = '27182818000128';
        $linhas = [
            ['nnf' => 987652001, 'tp' => '9', 'cstat' => 217],  // contingência: NÃO é rejeitada
            ['nnf' => 987652002, 'tp' => '9', 'cstat' => 462],  // rejeição real em nota offline
            ['nnf' => 987652003, 'tp' => '1', 'cstat' => 704],  // rejeição real, emissão normal
            ['nnf' => 987652004, 'tp' => '1', 'cstat' => 217],  // 217 de emissão NORMAL: segue rejeitada
        ];
        foreach ($linhas as $l) {
            FiscalStatus::create([
                'key' => '42' . '2607' . $cnpj . '65' . '001' . $l['nnf'] . $l['tp'] . '00000042' . '0',
                'model' => 65, 'cnpj_emit' => $cnpj, 'series' => 1, 'number' => $l['nnf'],
                'cstat' => $l['cstat'], 'category' => FiscalStatus::categoryFor($l['cstat']),
                'dh_recbto' => now(), 'environment_type' => '2', 'source' => 'sit',
            ]);
        }

        $achadas = FiscalStatus::query()->where('cnpj_emit', $cnpj)
            ->rejeitada()->orderBy('number')->pluck('number');

        $this->assertSame([987652002, 987652003, 987652004], $achadas->all());
    }

    /** A situação a MOSTRAR: uma nota tem UMA. Contingência ganha do catch-all "rejeitada". */
    public function test_categoria_efetiva_troca_rejeitada_por_contingencia(): void
    {
        $chave = fn (string $tp) => '42' . '2607' . '99887766000155' . '65' . '001' . '000123456' . $tp . '00000042' . '0';

        $conting = new FiscalStatus(['key' => $chave('9'), 'cstat' => 217, 'category' => 'rejeitada']);
        $rejeitada = new FiscalStatus(['key' => $chave('9'), 'cstat' => 462, 'category' => 'rejeitada']);
        $normal217 = new FiscalStatus(['key' => $chave('1'), 'cstat' => 217, 'category' => 'rejeitada']);

        $this->assertTrue($conting->estaEmContingencia());
        $this->assertSame('contingencia', $conting->categoriaEfetiva());

        $this->assertFalse($rejeitada->estaEmContingencia());
        $this->assertSame('rejeitada', $rejeitada->categoriaEfetiva());

        $this->assertFalse($normal217->estaEmContingencia(), 'emissao normal nunca e contingencia');
        $this->assertSame('rejeitada', $normal217->categoriaEfetiva());
    }

    /** O universo é a UNIÃO EXATA dos dois chips — sem linha órfã. */
    public function test_scope_rejeitada_ou_contingencia_e_a_uniao(): void
    {
        $cnpj = '16180339000188';
        $linhas = [
            ['nnf' => 987653001, 'tp' => '1', 'cstat' => 100],  // fora: autorizada
            ['nnf' => 987653002, 'tp' => '1', 'cstat' => 704],  // entra: rejeitada
            ['nnf' => 987653003, 'tp' => '9', 'cstat' => 100],  // fora: autorizada (mesmo offline)
            ['nnf' => 987653004, 'tp' => '9', 'cstat' => 217],  // entra: contingência
            ['nnf' => 987653005, 'tp' => '9', 'cstat' => 110],  // fora: denegada NÃO é 217 nem rejeitada
        ];
        foreach ($linhas as $l) {
            FiscalStatus::create([
                'key' => '42' . '2607' . $cnpj . '65' . '001' . $l['nnf'] . $l['tp'] . '00000042' . '0',
                'model' => 65, 'cnpj_emit' => $cnpj, 'series' => 1, 'number' => $l['nnf'],
                'cstat' => $l['cstat'], 'category' => FiscalStatus::categoryFor($l['cstat']),
                'dh_recbto' => now(), 'environment_type' => '2', 'source' => 'sit',
            ]);
        }

        $achadas = FiscalStatus::query()->where('cnpj_emit', $cnpj)
            ->rejeitadaOuContingencia()->orderBy('number')->pluck('number');

        $this->assertSame([987653002, 987653004], $achadas->all());
    }

    /**
     * Rejeitada de emissão normal continua no universo da aba Status SEFAZ.
     * É teste de comportamento, não da estrutura da query.
     */
    public function test_universo_nao_exclui_rejeitada_de_emissao_normal(): void
    {
        $cnpj = '16180339000188';
        FiscalStatus::create([
            'key' => '42' . '2607' . $cnpj . '65' . '001' . '987653001' . '1' . '00000042' . '0',
            'model' => 65, 'cnpj_emit' => $cnpj, 'series' => 1, 'number' => 987653001,
            'cstat' => 704, 'category' => 'rejeitada', 'dh_recbto' => now(),
            'environment_type' => '2', 'source' => 'pro-lot',
        ]);

        $achadas = FiscalStatus::query()->where('cnpj_emit', $cnpj)
            ->rejeitadaOuContingencia()->pluck('number');

        $this->assertSame([987653001], $achadas->all());
    }
}
