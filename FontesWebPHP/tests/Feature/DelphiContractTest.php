<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ⚠️ Contrato com o agente Delphi — não pode quebrar. Garante que os endpoints
 * de upload continuam exigindo a chave e validando o arquivo; sem isso o agente
 * entra em loop de reenvio.
 */
class DelphiContractTest extends TestCase
{
    /** Todos os endpoints de upload (5 docs + NFS-e + 4 eventos/inutilização + status). */
    private array $uploadRoutes = [
        '/api/docs/nfenfce/upload',
        '/api/docs/nfe/upload',
        '/api/docs/sat/upload',
        '/api/docs/cte/upload',
        '/api/docs/mdfe/upload',
        '/api/docs/nfse/upload',
        '/api/docs/eventos/nfenfce/upload',
        '/api/docs/eventos/cte/upload',
        '/api/docs/inutilizacao/nfenfce/upload',
        '/api/docs/inutilizacao/cte/upload',
        '/api/docs/status/upload',
        '/api/docs/status-erp/upload',
        '/api/docs/nfse-erp/upload',
    ];

    /** Uploads de documento que validam o arquivo XML (retornam 422 sem file). */
    private array $docUploadRoutes = [
        '/api/docs/nfenfce/upload',
        '/api/docs/nfe/upload',
        '/api/docs/sat/upload',
        '/api/docs/cte/upload',
        '/api/docs/mdfe/upload',
        '/api/docs/nfse/upload',
        '/api/docs/status/upload',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.system_access_key' => 'Sistema']);
    }

    public function test_todos_os_uploads_negam_sem_a_chave(): void
    {
        foreach ($this->uploadRoutes as $route) {
            $response = $this->post($route);

            $response->assertStatus(403);

            // O contrato é msg != '100', que é o que impede o agente de marcar
            // o arquivo como enviado. O texto em si pode mudar.
            $this->assertNotSame(
                '100',
                $response->json('msg'),
                "A rota {$route} nao pode responder msg=100 sem a chave."
            );
            $this->assertNotEmpty($response->json('msg'), "A rota {$route} deve explicar a recusa.");
        }
    }

    public function test_uploads_de_documento_validam_arquivo_com_chave_valida(): void
    {
        foreach ($this->docUploadRoutes as $route) {
            $response = $this->post($route, ['key' => 'Sistema']);

            $response->assertStatus(422);
            $this->assertSame(
                'Arquivo XML invalido ou ausente.',
                $response->json('msg'),
                "A rota {$route} deveria responder 422 com a chave e sem arquivo."
            );
        }
    }

    /**
     * 🛑 O AGENTE DEPENDE DESTES DOIS STATUS PARA CONFERIR A CHAVE (2026-07-31).
     *
     * Chave preenchida não quer dizer chave certa: um cliente digitou "teste" no
     * campo e a varredura rodou inteira levando 403 em cada arquivo — nada era
     * gravado e, na tela do agente, parecia normal.
     *
     * O agente passou a sondar ANTES de varrer, com um POST que leva só a chave
     * e nenhum arquivo (`uFileUploadXML.ValidaChave`). A decisão é pelo STATUS,
     * não pelo texto:
     *
     *     403 -> chave errada  -> SUSPENDE os envios e mostra o motivo
     *     422 -> chave aceita  -> varre normal
     *     404 -> URL errada    -> não é problema de chave, segue
     *
     * Escolhemos uma rota que já existe (em vez de um endpoint novo) justamente
     * para o agente novo funcionar contra portal ANTIGO. Se estes status mudarem,
     * o agente para de detectar chave errada — ou pior, passa a suspender envio
     * de cliente com a chave certa.
     */
    public function test_a_sondagem_de_chave_do_agente_distingue_403_de_422(): void
    {
        $rota = '/api/docs/nfenfce/upload';

        // Chave errada: o agente lê 403 e suspende.
        $this->post($rota, ['key' => 'chave-que-nao-existe'])->assertStatus(403);

        // Chave certa, sem arquivo: NÃO pode ser 403, senão o agente suspenderia
        // o envio de um cliente que está com tudo certo.
        $this->post($rota, ['key' => 'Sistema'])->assertStatus(422);
    }

    /** E a recusa precisa DIZER o que fazer — é o texto que o agente exibe. */
    public function test_o_403_explica_como_resolver(): void
    {
        $msg = $this->post('/api/docs/nfenfce/upload', ['key' => 'errada'])
            ->json('msg');

        $this->assertNotSame('100', $msg);
        $this->assertStringContainsStringIgnoringCase('chave', (string) $msg);
        $this->assertStringContainsStringIgnoringCase('painel', (string) $msg);
    }
}
