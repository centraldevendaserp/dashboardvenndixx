<?php

namespace Tests\Feature\Api;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * A chave legada não pode valer "de fábrica": com um default no config, a chave
 * publicada na documentação seria aceita em portal que não configurou nada — e
 * o rate limiter isenta a chave legada. Sem a variável, nenhuma chave vale.
 */
class ChaveLegadaDefaultTest extends TestCase
{
    private function envia(string $chave)
    {
        return $this->post('/api/docs/nfenfce/upload', [
            'key'  => $chave,
            'file' => UploadedFile::fake()->createWithContent('x.xml', '<a/>'),
        ]);
    }

    /** Sem a variável no ambiente, `Sistema` não abre mais a porta. */
    public function test_sem_variavel_configurada_a_chave_publica_nao_vale(): void
    {
        config(['app.system_access_key' => null]);

        $this->envia('Sistema')->assertStatus(403);
    }

    /** Com o kill switch desligado também não, mesmo com a variável certa. */
    public function test_kill_switch_desligado_recusa(): void
    {
        config(['app.legacy_key_enabled' => false]);

        $this->envia('Sistema')->assertStatus(403);
    }

    /** A recusa tem de dizer o que fazer: o log do agente é tudo que quem instala vê. */
    public function test_a_recusa_orienta_o_operador(): void
    {
        config(['app.system_access_key' => null]);

        $resposta = $this->envia('Sistema');

        $this->assertNotSame('100', $resposta->json('msg'), 'nunca msg=100 numa recusa');
        $this->assertStringContainsString('.ini', $resposta->json('msg'));
        $this->assertStringContainsString('painel', $resposta->json('msg'));
    }

    /** Instalação que declara a chave continua funcionando — ninguém quebra. */
    public function test_cliente_que_declara_a_chave_continua_entrando(): void
    {
        config(['app.system_access_key' => 'Sistema', 'app.legacy_key_enabled' => true]);

        $this->envia('Sistema')->assertStatus(422);   // passou da credencial; barrou no XML
    }
}
