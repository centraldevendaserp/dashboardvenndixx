<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Upload de XML bem-formado mas FORA do layout responde 422, não 500. Sem o
 * guard, o import quebra em "property on null" e o agente re-tenta para sempre,
 * enchendo o log.
 */
class XmlSchemaGuardTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** XML bem-formado, mas de outro schema (sem os nós fiscais esperados). */
    private function xmlForaDoSchema(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><documento><foo>bar</foo></documento>';
    }

    private function envia(string $rota): \Illuminate\Testing\TestResponse
    {
        return $this->post("/api/docs/{$rota}", [
            'key'  => 'Sistema',
            'file' => UploadedFile::fake()->createWithContent('estranho.xml', $this->xmlForaDoSchema()),
        ]);
    }

    public function test_nfenfce_xml_fora_do_schema_retorna_422(): void
    {
        $this->envia('nfenfce/upload')->assertStatus(422);
    }

    public function test_nfe_entrada_xml_fora_do_schema_retorna_422(): void
    {
        $this->envia('nfe/upload')->assertStatus(422);
    }

    public function test_sat_xml_fora_do_schema_retorna_422(): void
    {
        $this->envia('sat/upload')->assertStatus(422);
    }

    public function test_cte_xml_fora_do_schema_retorna_422(): void
    {
        $this->envia('cte/upload')->assertStatus(422);
    }

    public function test_mdfe_xml_fora_do_schema_retorna_422(): void
    {
        $this->envia('mdfe/upload')->assertStatus(422);
    }

    /* --- eventos/inutilizações (EventsController — mesmo guard V2-2) --- */

    public function test_evento_nfenfce_xml_fora_do_schema_retorna_422(): void
    {
        $this->envia('eventos/nfenfce/upload')->assertStatus(422);
    }

    public function test_evento_cte_xml_fora_do_schema_retorna_422(): void
    {
        $this->envia('eventos/cte/upload')->assertStatus(422);
    }

    public function test_inutilizacao_nfenfce_xml_fora_do_schema_retorna_422(): void
    {
        $this->envia('inutilizacao/nfenfce/upload')->assertStatus(422);
    }

    public function test_inutilizacao_cte_xml_fora_do_schema_retorna_422(): void
    {
        $this->envia('inutilizacao/cte/upload')->assertStatus(422);
    }

    public function test_xml_fora_do_schema_nao_grava_documento(): void
    {
        $antes = DB::table('documents')->count();
        $this->envia('nfenfce/upload');
        $this->assertSame($antes, DB::table('documents')->count(), 'XML inválido não pode gravar documento.');
    }
}
