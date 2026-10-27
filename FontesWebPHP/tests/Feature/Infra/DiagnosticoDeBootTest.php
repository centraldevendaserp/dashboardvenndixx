<?php

namespace Tests\Feature\Infra;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * O "500 em branco" na instalação: com APP_DEBUG=false o Laravel não conta o que
 * houve, e as causas comuns em hospedagem compartilhada (PHP velho, `vendor/`
 * ausente, extensão faltando, `storage/` sem escrita) acontecem ANTES de ele
 * existir. Sem terminal, não há como investigar.
 *
 * O diagnóstico roda antes do autoload e troca o 500 mudo por uma página que diz
 * o que fazer; ambiente sadio sai calado.
 */
class DiagnosticoDeBootTest extends TestCase
{
    private function arquivo(): string
    {
        return File::get(base_path('bootstrap/verifica-ambiente.php'));
    }

    /** Roda ANTES do autoload — depois dele já é tarde para os casos que importam. */
    public function test_index_chama_antes_do_autoload(): void
    {
        $index = File::get(public_path('index.php'));

        $posDiag = strpos($index, 'verifica-ambiente.php');
        $posAuto = strpos($index, 'vendor/autoload.php');

        $this->assertNotFalse($posDiag, 'o index.php não chama o diagnóstico');
        $this->assertNotFalse($posAuto);
        $this->assertLessThan(
            $posAuto,
            $posDiag,
            'o diagnóstico tem de vir ANTES do autoload — vendor ausente quebra justamente nessa linha'
        );
    }

    /**
     * ⚠️ O diagnóstico roda em PHP 7.4, que é um dos casos que ele existe para
     * diagnosticar: sintaxe de PHP 8 aqui faria o próprio diagnóstico virar
     * erro de sintaxe, e a pessoa continuaria vendo 500.
     */
    public function test_sintaxe_compativel_com_php_antigo(): void
    {
        // php_strip_whitespace tira os comentários pelo tokenizer: o docblock do
        // arquivo cita `?->` e `match` ao explicar que não pode usá-los, e sem
        // isso o teste acusaria o próprio aviso.
        $php = php_strip_whitespace(base_path('bootstrap/verifica-ambiente.php'));

        foreach ([
            'match ('        => 'match é PHP 8',
            '?->'            => 'nullsafe é PHP 8',
            'fn ('           => 'arrow function é PHP 7.4+',
            'readonly '      => 'readonly é PHP 8.1',
            '#['             => 'atributo é PHP 8',
        ] as $agulha => $porque) {
            $this->assertStringNotContainsString($agulha, $php, "{$porque} — quebra em PHP 7.4");
        }

        // `[]` de array curto é PHP 5.4+, mas o arquivo usa array() por disciplina:
        // é o sinal mais visível de que ele não pode "modernizar".
        $this->assertStringContainsString('array(', $php);

        // Tipo de retorno/propriedade e `??` também não passam em PHP 5.6.
        $this->assertStringNotContainsString('??', $php);
    }

    /** As causas que o suporte viu de verdade precisam estar cobertas. */
    public function test_cobre_as_causas_reais(): void
    {
        $php = $this->arquivo();

        $this->assertStringContainsString("version_compare(PHP_VERSION, '8.2.0'", $php, 'PHP velho');
        $this->assertStringContainsString('/vendor/autoload.php', $php, 'vendor ausente');
        $this->assertStringContainsString("'soap'", $php, 'soap já derrubou a instalação antes');
        $this->assertStringContainsString("/.env", $php, '.env ausente');
        $this->assertStringContainsString('is_writable', $php, 'storage sem escrita');
    }

    /** Ambiente sadio não pode imprimir nada — senão quebra o site que funciona. */
    public function test_ambiente_sadio_sai_calado(): void
    {
        ob_start();
        require base_path('bootstrap/verifica-ambiente.php');
        $saida = ob_get_clean();

        $this->assertSame('', $saida, 'o diagnóstico imprimiu num ambiente sadio');
    }

    /**
     * Sem APP_KEY o Laravel não sobe — 500 até no /install, que é justamente
     * quem geraria a chave: um laço fechado para quem não tem terminal.
     *
     * ⚠️ Gerar só é seguro com a chave VAZIA: nada foi cifrado com ela ainda.
     * Sobrescrever chave existente mataria sessões e todo dado cifrado.
     */
    public function test_gera_app_key_quando_vazia_e_nunca_sobrescreve(): void
    {
        $dir = storage_path('framework/testing/boot-' . getmypid());
        File::ensureDirectoryExists($dir . '/bootstrap');
        File::copy(base_path('bootstrap/verifica-ambiente.php'), $dir . '/bootstrap/verifica-ambiente.php');

        $rodar = function (string $env) use ($dir): string {
            File::put($dir . '/.env', $env);
            // Subprocesso: o script dá `exit` quando acha problema, e o `$raiz`
            // dele é derivado de __DIR__ — precisa da árvore falsa, não da real.
            exec('php ' . escapeshellarg($dir . '/bootstrap/verifica-ambiente.php') . ' 2>&1');

            return File::get($dir . '/.env');
        };

        // 1. vazia -> gera
        $depois = $rodar("APP_ENV=production
APP_KEY=
APP_URL=https://x.test
");
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:.{40,}$/m', $depois, 'não gerou a chave');

        // 2. ausente -> acrescenta
        $depois = $rodar("APP_ENV=production
APP_URL=https://x.test
");
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:.{40,}$/m', $depois, 'não acrescentou a chave');

        // 3. JÁ EXISTE -> não encosta. Esta é a que protege dado cifrado.
        $existente = 'base64:' . base64_encode(str_repeat('k', 32));
        $depois = $rodar("APP_ENV=production
APP_KEY={$existente}
");
        $this->assertStringContainsString(
            'APP_KEY=' . $existente,
            $depois,
            'sobrescreveu uma APP_KEY existente — isso mata sessões e dado cifrado'
        );

        File::deleteDirectory($dir);
    }

    /** 503, não 200: é um estado temporário de servidor, e buscador não indexa. */
    public function test_responde_503(): void
    {
        $this->assertStringContainsString('503 Service Unavailable', $this->arquivo());
    }
}
