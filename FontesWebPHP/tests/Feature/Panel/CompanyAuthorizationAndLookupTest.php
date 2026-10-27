<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Company\ModalDataToCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Autorização do modal de empresa e a consulta de CEP/CNPJ. As chamadas HTTP
 * externas são fakeadas, para o teste não depender de rede.
 */
class CompanyAuthorizationAndLookupTest extends TestCase
{
    use DatabaseTransactions;

    public function test_nao_admin_e_bloqueado_no_modal_de_empresa(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 'N']));

        // mount() chama ensureAdmin() -> aborta 403 (capturado como resposta no teste).
        Livewire::test(ModalDataToCompany::class)->assertForbidden();
    }

    public function test_admin_pode_abrir_o_modal_de_empresa(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 'S']));

        Livewire::test(ModalDataToCompany::class)
            ->call('eventAction', 'store')
            ->assertOk();
    }

    public function test_buscar_cnpj_preenche_os_campos(): void
    {
        Http::fake([
            '*brasilapi.com.br/api/cnpj/v1/*' => Http::response([
                'razao_social' => 'EMPRESA TESTE LTDA',
                'nome_fantasia' => 'Teste',
                'cep' => '01310100',
                'logradouro' => 'Avenida Teste',
                'numero' => '100',
                'complemento' => 'Sala 1',
                'bairro' => 'Centro',
                'municipio' => 'SAO PAULO',
                'uf' => 'SP',
                'ddd_telefone_1' => '1133334444',
                'email' => 'contato@teste.com',
            ], 200),
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => 'S']));

        Livewire::test(ModalDataToCompany::class)
            ->set('cnpj_cpf', '00.000.000/0001-91')
            ->call('buscarCnpj')
            ->assertSet('corporate_name', 'EMPRESA TESTE LTDA')
            ->assertSet('fantasy_name', 'Teste')
            ->assertSet('public_place', 'Avenida Teste')
            ->assertSet('district', 'Centro')
            ->assertSet('county', 'SAO PAULO')
            ->assertSet('uf', 'SP')
            ->assertSet('email', 'contato@teste.com');
    }

    public function test_buscar_cep_preenche_o_endereco(): void
    {
        Http::fake([
            '*brasilapi.com.br/api/cep/v2/*' => Http::response([
                'cep' => '01310100',
                'state' => 'SP',
                'city' => 'São Paulo',
                'neighborhood' => 'Bela Vista',
                'street' => 'Avenida Paulista',
            ], 200),
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => 'S']));

        Livewire::test(ModalDataToCompany::class)
            ->set('zip_code', '01310-100')
            ->call('buscarCep')
            ->assertSet('public_place', 'Avenida Paulista')
            ->assertSet('district', 'Bela Vista')
            ->assertSet('county', 'São Paulo')
            ->assertSet('uf', 'SP');
    }

    public function test_buscar_cnpj_invalido_nao_preenche(): void
    {
        Http::fake(); // garante que nenhuma chamada real sai

        $this->actingAs(User::factory()->create(['is_admin' => 'S']));

        Livewire::test(ModalDataToCompany::class)
            ->set('cnpj_cpf', '123') // menos de 14 dígitos
            ->call('buscarCnpj')
            ->assertSet('corporate_name', null);

        Http::assertNothingSent();
    }
}
