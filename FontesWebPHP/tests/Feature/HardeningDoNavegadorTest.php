<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guardas do que não dá para exercitar aqui: JS que só roda em navegador e
 * `.htaccess`, que quem interpreta é o Apache. Sem esta rede, a regressão só
 * apareceria em produção.
 */
class HardeningDoNavegadorTest extends TestCase
{
    private function cuteAlert(): string
    {
        return File::get(public_path('assets/plugins/cute-alert/cute-alert.js'));
    }

    /**
     * `title`/`message` entram no DOM por `insertAdjacentHTML`, então marcação
     * no meio vira elemento de verdade. Hoje toda mensagem é literal, mas a
     * próxima pode carregar razão social — que vem do XML importado.
     */
    public function test_cute_alert_nao_interpola_texto_no_html(): void
    {
        $js = $this->cuteAlert();

        $this->assertStringNotContainsString('${message}', $js, 'mensagem voltou a ser interpolada como HTML');
        $this->assertStringNotContainsString('${title}', $js, 'título voltou a ser interpolado como HTML');
    }

    /** E o texto tem de continuar APARECENDO — remover a interpolação sem repor esvazia o alerta. */
    public function test_cute_alert_ainda_mostra_o_texto(): void
    {
        $js = $this->cuteAlert();

        foreach (['.alert-title', '.alert-message', '.toast-message'] as $seletor) {
            $this->assertMatchesRegularExpression(
                '/querySelector\("' . preg_quote($seletor, '/') . '"\)\.textContent\s*=/',
                $js,
                "{$seletor} ficou sem o textContent — o alerta renderiza vazio"
            );
        }
    }

    /**
     * `/assets/*` e `/storage/*` são servidos pelo Apache, sem PHP no caminho:
     * o middleware não os alcança, e `/storage/` é a pasta publicada pelo
     * `storage:link`.
     */
    public function test_htaccess_publico_manda_cabecalho_no_estatico(): void
    {
        $ht = File::get(public_path('.htaccess'));

        $this->assertStringContainsString('mod_headers.c', $ht, 'sem IfModule, Apache sem mod_headers dá 500');
        $this->assertStringContainsString('X-Content-Type-Options', $ht);
        $this->assertStringContainsString('X-Frame-Options', $ht);
    }

    /**
     * ⚠️ `setifempty`, nunca `set`: nas respostas que passam pelo Laravel o
     * middleware já põe os mesmos cabeçalhos, e dois `X-Frame-Options` o
     * navegador pode tratar como conflito e ignorar.
     */
    public function test_htaccess_nao_duplica_o_que_o_php_ja_manda(): void
    {
        $ht = File::get(public_path('.htaccess'));

        foreach (['X-Content-Type-Options', 'X-Frame-Options', 'Content-Security-Policy'] as $cabecalho) {
            $this->assertMatchesRegularExpression(
                '/Header\s+setifempty\s+' . preg_quote($cabecalho, '/') . '/',
                $ht,
                "{$cabecalho} com `set` duplica o cabeçalho do middleware"
            );
        }
    }

    /** O bloco do cPanel fixando PHP 7.4 não pode voltar (o projeto exige ^8.2). */
    public function test_htaccess_sem_bloco_do_cpanel(): void
    {
        $this->assertStringNotContainsString(
            'AddHandler application/x-httpd-ea-php',
            File::get(public_path('.htaccess'))
        );
    }
}
