<?php

namespace Tests\Feature\Panel;

use App\Models\FiscalStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Nota emitida em contingência e depois reemitida em modo normal: a chave antiga
 * nunca foi transmitida e responde 217 para sempre.
 *
 * Tecnicamente ela ainda está em contingência, mas é um fantasma — anunciá-la
 * como pendência manda o contador procurar o que não existe.
 */
class ContingenciaSubstituidaTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '09617165000181';

    /**
     * Monta a chave de 44 no layout real, porque o tpEmis é posicional:
     * cUF(2) AAMM(4) CNPJ(14) mod(2) serie(3) nNF(9) tpEmis(1) cNF(8) cDV(1).
     */
    private function chave(string $numero, string $tpEmis, string $sufixo): string
    {
        $chave = '42' . '2605' . self::CNPJ . '65' . '001'
            . str_pad($numero, 9, '0', STR_PAD_LEFT)
            . $tpEmis
            . str_pad($sufixo, 8, '0', STR_PAD_LEFT)
            . '0';

        // Se o layout mudar, o teste tem de gritar aqui e não numa asserção
        // confusa lá embaixo.
        $this->assertSame(44, strlen($chave));
        $this->assertSame($tpEmis, substr($chave, 34, 1), 'tpEmis precisa cair no digito 35.');

        return $chave;
    }

    private function statusContingencia(string $chave, string $numero): FiscalStatus
    {
        return FiscalStatus::create([
            'key' => $chave, 'model' => 65, 'cnpj_emit' => self::CNPJ,
            'series' => 1, 'number' => (int) $numero,
            'cstat' => FiscalStatus::CSTAT_NAO_CONSTA, 'category' => 'rejeitada',
            'x_motivo' => 'Rejeicao: NF-e nao consta na base de dados da SEFAZ',
            'source' => 'xml',
        ]);
    }

    /** `documents` tem varias colunas NOT NULL sem default - preenche todas. */
    private function insereDocumento(string $chave, string $numero, int $status, int $serie = 1): void
    {
        DB::table('documents')->insert([
            'key' => $chave, 'cnpj_cpf' => self::CNPJ, 'ie' => '123456789',
            'model' => 65, 'series' => $serie, 'number' => (int) $numero,
            'month_year' => '202705', 'issue_dh' => '2027-05-10',
            'path_xml' => '/docs/teste.xml', 'protocol' => '142270000000001',
            'environment_type' => '1', 'status_xml' => $status, 'vNF' => 10.00,
        ]);
    }

    private function notaAutorizada(string $chave, string $numero): void
    {
        $this->insereDocumento($chave, $numero, 100);
    }

    /** Sem a reemissão normal, a chave de contingência ESTÁ pendente de verdade. */
    public function test_contingencia_sem_substituicao_continua_em_contingencia(): void
    {
        $status = $this->statusContingencia($this->chave('009901', '9', '32648087'), '009901');

        $this->assertTrue($status->estaEmContingencia());
        $this->assertSame('contingencia', $status->categoriaEfetiva());
    }

    /** Com a MESMA nota autorizada por outra chave, a de contingência é fantasma. */
    public function test_contingencia_substituida_por_autorizada_nao_conta(): void
    {
        $numero = '009902';
        $this->notaAutorizada($this->chave($numero, '1', '32648094'), $numero);
        $status = $this->statusContingencia($this->chave($numero, '9', '32648090'), $numero);

        $this->assertFalse($status->estaEmContingencia(),
            'Nota reemitida em modo normal e autorizada: a chave de contingencia foi substituida.');
        $this->assertSame('rejeitada', $status->categoriaEfetiva());
    }

    /** Nota de OUTRO número autorizada não pode "limpar" esta contingência. */
    public function test_autorizada_de_outro_numero_nao_afeta(): void
    {
        $this->notaAutorizada($this->chave('009904', '1', '32648099'), '009904');
        $status = $this->statusContingencia($this->chave('009903', '9', '32648091'), '009903');

        $this->assertTrue($status->estaEmContingencia());
    }

    /** Mesma numeração em série diferente também não conta. */
    public function test_serie_diferente_nao_afeta(): void
    {
        $numero = '009905';
        $this->insereDocumento($this->chave($numero, '1', '32648095'), $numero, 100, serie: 2);
        $status = $this->statusContingencia($this->chave($numero, '9', '32648096'), $numero);

        $this->assertTrue($status->estaEmContingencia());
    }

    /** A substituta precisa estar AUTORIZADA — cancelada não vale. */
    public function test_substituta_cancelada_nao_limpa_a_contingencia(): void
    {
        $numero = '009906';
        $this->insereDocumento($this->chave($numero, '1', '32648097'), $numero, 101);
        $status = $this->statusContingencia($this->chave($numero, '9', '32648098'), $numero);

        $this->assertTrue($status->estaEmContingencia());
    }
}
