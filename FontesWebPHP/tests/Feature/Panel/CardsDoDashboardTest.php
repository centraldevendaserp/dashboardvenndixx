<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Dashboard\CardInfo;
use App\Livewire\Panel\Dashboard\CardInfoDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CardsDoDashboardTest extends TestCase
{
    use DatabaseTransactions;

    /** Os dois componentes são `lazy` no dashboard — sem isto o teste vê o esqueleto. */
    protected function setUp(): void
    {
        parent::setUp();
        Livewire::withoutLazyLoading();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@gmail.com')->firstOrFail();
    }

    /** Posição de cada rótulo no HTML — é a ordem em que aparecem na tela. */
    private function ordem(string $html, array $rotulos): array
    {
        $pos = [];
        foreach ($rotulos as $r) {
            $i = strpos($html, $r);
            $this->assertNotFalse($i, "rótulo não encontrado no HTML: {$r}");
            $pos[$r] = $i;
        }
        asort($pos);

        return array_keys($pos);
    }

    /** "Faturamento por modelo" na mesma ordem da sidebar e do agente. */
    public function test_cards_de_modelo_na_ordem_pedida(): void
    {
        $html = Livewire::actingAs($this->admin())->test(CardInfoDocument::class, ['user' => $this->admin()])->html();

        $this->assertSame(
            ['NFC-e', 'NF-e', 'NFS-e', 'MDF-e', 'CT-e', 'Entrada/Compras'],
            $this->ordem($html, ['NFC-e', 'NF-e', 'NFS-e', 'MDF-e', 'CT-e', 'Entrada/Compras'])
        );
    }

    /** "Usuários"/"Empresas" sozinhos não diziam de QUE recorte eram. */
    public function test_kpis_dizem_que_sao_totais(): void
    {
        $html = Livewire::actingAs($this->admin())->test(CardInfo::class, ['user' => $this->admin()])->html();

        $this->assertStringContainsString('Usuários totais', $html);
        $this->assertStringContainsString('Empresas totais', $html);
    }

    /** Clicáveis, e com `wire:navigate` — a mesma transição dos links da sidebar. */
    public function test_kpis_de_cadastro_levam_para_a_tela(): void
    {
        $html = Livewire::actingAs($this->admin())->test(CardInfo::class, ['user' => $this->admin()])->html();

        foreach ([route('panel.users.index'), route('panel.companies.index')] as $url) {
            $this->assertMatchesRegularExpression(
                '/<a[^>]+href="' . preg_quote($url, '/') . '"[^>]*wire:navigate/',
                $html,
                "faltou o link com wire:navigate para {$url}"
            );
        }
    }

    /**
     * Todo link da sidebar usa wire:navigate: senão o mesmo destino se comporta
     * de dois jeitos conforme a porta de entrada.
     */
    public function test_sidebar_navega_igual_para_as_telas_de_cadastro(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/general/menu-sidebar.blade.php'));

        foreach (['panel.companies.index', 'panel.users.index'] as $rota) {
            $this->assertMatchesRegularExpression(
                '/route\(\'' . preg_quote($rota, '/') . '\'\) \}\}" wire:navigate/',
                $blade,
                "{$rota} na sidebar sem wire:navigate — recarrega a página, ao contrário do card"
            );
        }
    }

    /**
     * Não-admin leva 403 em /panel/users e /panel/companies. O card de Empresas
     * aparece para ele, então continua card sem link — senão o clique cairia
     * numa tela de erro.
     */
    public function test_nao_admin_ve_o_card_mas_sem_link(): void
    {
        $comum = User::factory()->create(['is_admin' => 'N']);

        $html = Livewire::actingAs($comum)->test(CardInfo::class, ['user' => $comum])->html();

        $this->assertStringContainsString('Empresas totais', $html);
        $this->assertStringNotContainsString(route('panel.companies.index'), $html);
        // O de usuários nunca foi exibido para não-admin.
        $this->assertStringNotContainsString('Usuários totais', $html);
    }
}
