<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Company\ModalDataToCompany;
use App\Livewire\Panel\User\ModalDataToUser;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Clicar em "Editar" dispara duas coisas que não se falam: o JS do tema abre o
 * modal na hora, e o wire:click só traz o conteúdo certo um round-trip depois —
 * no intervalo aparece o registro ANTERIOR.
 *
 * A cobertura possível aqui é de FONTE: as três peças do mecanismo têm de
 * continuar existindo e casando entre si.
 */
class ModalEsperaDadoTest extends TestCase
{
    private function layout(): string
    {
        return File::get(resource_path('views/components/layouts/app.blade.php'));
    }

    /** Peça 1: a classe entra no MESMO clique que o tema usa para abrir. */
    public function test_marca_espera_no_clique_do_gatilho_do_tema(): void
    {
        $js = $this->layout();

        $this->assertStringContainsString("closest('[data-trigger=\"modal\"]')", $js);
        $this->assertStringContainsString("classList.add('is-esperando-dado')", $js);
        $this->assertStringContainsString(
            '}, true);',
            $js,
            'o listener precisa ser de CAPTURA — sem isso ele roda depois do tema e o modal pisca'
        );
    }

    /**
     * Peça 2: quem revela é o evento que o servidor dispara no fim do
     * eventAction, não um timer — timer erra nos dois sentidos.
     */
    public function test_revela_pelo_evento_do_servidor(): void
    {
        $js = $this->layout();

        $this->assertStringContainsString("Livewire.on('configureUserModal'", $js);
        $this->assertStringContainsString("Livewire.on('syncCompanyModalFields'", $js);
    }

    /** E os dois eventos precisam continuar saindo do servidor. */
    public function test_servidor_avisa_quando_o_dado_do_usuario_chega(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(ModalDataToUser::class)
            ->call('eventAction', ['action' => 'edit', 'user_id' => $admin->id])
            ->assertDispatched('configureUserModal');
    }

    public function test_servidor_avisa_quando_o_dado_da_empresa_chega(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(ModalDataToCompany::class)
            ->call('eventAction', 'store')
            ->assertDispatched('syncCompanyModalFields');
    }

    /**
     * Peça 3: requisição que nunca volta não pode deixar o modal em branco para
     * sempre — conteúdo velho é melhor que nada.
     */
    public function test_tem_rede_de_seguranca(): void
    {
        $this->assertMatchesRegularExpression(
            "/setTimeout\(function \(\) \{\s*modal\.classList\.remove\('is-esperando-dado'\);\s*\}, \d+\);/",
            $this->layout(),
            'sem o timeout de escape, um erro de rede deixa o modal em branco para sempre'
        );
    }

    /** O CSS esconde sem mudar o tamanho, e o X continua alcançável. */
    public function test_css_esconde_sem_pular_e_mantem_o_fechar(): void
    {
        $css = File::get(public_path('assets/css/custom.css'));

        $this->assertStringContainsString(
            'Modal de cadastro esperando o dado',
            $css,
            'o bloco de CSS do estado de espera sumiu'
        );

        // Tira os comentários antes de procurar: `display: none` aparece de
        // propósito no texto que explica por que ele NÃO foi usado.
        $regras = preg_replace('#/\*.*?\*/#s', '', $css);

        $i = strpos($regras, '.is-esperando-dado');
        $this->assertNotFalse($i, 'nenhuma REGRA usa .is-esperando-dado');

        $bloco = substr($regras, $i);

        $this->assertStringContainsString('visibility: hidden', $bloco);
        $this->assertStringNotContainsString(
            'display: none',
            $bloco,
            'display:none faz o modal pular de tamanho quando o dado chega'
        );
        $this->assertStringNotContainsString('a.close', $bloco, 'o X não pode ser escondido');
    }
}
