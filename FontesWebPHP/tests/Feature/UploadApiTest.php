<?php

namespace Tests\Feature;

use Tests\TestCase;

class UploadApiTest extends TestCase
{
    public function test_docs_upload_requires_system_key()
    {
        config(['app.system_access_key' => 'Sistema']);

        $response = $this->post('/api/docs/nfenfce/upload');

        $response->assertStatus(403);
        $response->assertJson([
            'msg' => 'Chave de acesso invalida. Gere a chave do agente no painel '
                . '(ao cadastrar/editar o usuario) e cole o valor no Key= do .ini.'
        ]);
    }

    public function test_docs_upload_returns_validation_error_when_file_is_missing()
    {
        config(['app.system_access_key' => 'Sistema']);

        $response = $this->post('/api/docs/nfenfce/upload', [
            'key' => 'Sistema',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'msg' => 'Arquivo XML invalido ou ausente.'
        ]);
    }
}
