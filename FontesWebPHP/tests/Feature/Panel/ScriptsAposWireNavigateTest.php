<?php

namespace Tests\Feature\Panel;

use Tests\TestCase;

/**
 * `livewire:init` / `livewire:initialized` disparam UMA vez por carregamento
 * REAL da página: quem chega por `wire:navigate` encontra o evento já disparado,
 * e o que estiver registrado dentro dele nunca roda.
 *
 * O padrão correto é sempre:
 *   if (window.Livewire) iniciar(); else document.addEventListener('livewire:init…', iniciar);
 *
 * Teste de FONTE, não de navegador: não prova que a tela funciona, prova que
 * ninguém reintroduziu o padrão que a quebra.
 */
class ScriptsAposWireNavigateTest extends TestCase
{
    /** Blades que registram algo dependente do ciclo do Livewire. */
    private const ARQUIVOS = [
        'livewire/panel/dashboard/index.blade.php',
        'livewire/panel/user/modal-data-to-user.blade.php',
        // Entrou quando o card "Empresas totais" virou link: antes nada chegava
        // a /panel/companies por navegação SPA e o defeito ficava latente.
        'livewire/panel/company/modal-data-to-company.blade.php',
    ];

    public function test_scripts_tem_caminho_para_livewire_ja_iniciado(): void
    {
        foreach (self::ARQUIVOS as $rel) {
            $blade = file_get_contents(resource_path('views/' . $rel));

            $this->assertStringContainsString(
                'if (window.Livewire)',
                $blade,
                "{$rel}: sem o caminho alternativo para o Livewire já iniciado — quem chegar "
                . 'aqui por wire:navigate vai encontrar o script sem efeito.'
            );
        }
    }

    /**
     * Nenhum `Livewire.on(` pode ser a primeira coisa dentro de um
     * addEventListener de `livewire:init*` — é exatamente a forma que falha.
     */
    public function test_nenhum_listener_preso_direto_no_livewire_init(): void
    {
        foreach (self::ARQUIVOS as $rel) {
            $blade = file_get_contents(resource_path('views/' . $rel));

            $this->assertDoesNotMatchRegularExpression(
                '/addEventListener\(\s*[\'"]livewire:init(ialized)?[\'"]\s*,\s*(function\s*\(\)\s*\{|\(\)\s*=>\s*\{)\s*Livewire\.on\(/',
                $blade,
                "{$rel}: Livewire.on preso dentro do livewire:init — não roda em wire:navigate."
            );
        }
    }
}
