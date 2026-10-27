<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Company\Index as CompanyIndex;
use App\Livewire\Panel\User\Index as UserIndex;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Busca nas listagens de Usuários (nome, e-mail, CNPJ de empresa vinculada) e
 * Empresas (razão social, nome fantasia, CNPJ).
 */
class PanelSearchTest extends TestCase
{
    use DatabaseTransactions;

    private function actingAdmin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 'S']));
    }

    public function test_busca_de_usuario_por_nome(): void
    {
        $this->actingAdmin();
        User::factory()->create(['is_admin' => 'N', 'name' => 'Aurelio Zxqwk']);
        User::factory()->create(['is_admin' => 'N', 'name' => 'Belarmino Yptlu']);

        Livewire::test(UserIndex::class)
            ->set('search', 'Aurelio Zxqwk')
            ->assertSee('Aurelio Zxqwk')
            ->assertDontSee('Belarmino Yptlu');
    }

    public function test_busca_de_usuario_por_cnpj_de_empresa_vinculada(): void
    {
        $this->actingAdmin();

        $company = Company::create([
            'corporate_name' => 'Empresa Vinculo Teste LTDA',
            'cnpj_cpf' => '12345678000199',
        ]);
        $carol = User::factory()->create(['is_admin' => 'N', 'name' => 'Carolina Wkpzr']);
        $carol->companies()->attach($company->id);

        // Digitado com máscara: deve casar pelos dígitos do CNPJ.
        Livewire::test(UserIndex::class)
            ->set('search', '12.345.678/0001-99')
            ->assertSee('Carolina Wkpzr');
    }

    public function test_busca_de_empresa_por_nome(): void
    {
        $this->actingAdmin();
        Company::create(['corporate_name' => 'Padaria Klmnq', 'cnpj_cpf' => '11222333000181']);
        Company::create(['corporate_name' => 'Mercado Vbxzt', 'cnpj_cpf' => '99888777000166']);

        Livewire::test(CompanyIndex::class)
            ->set('search', 'Padaria Klmnq')
            ->assertSee('Padaria Klmnq')
            ->assertDontSee('Mercado Vbxzt');
    }

    public function test_busca_de_empresa_por_cnpj(): void
    {
        $this->actingAdmin();
        Company::create(['corporate_name' => 'Mercado Vbxzt SA', 'cnpj_cpf' => '99888777000166']);

        Livewire::test(CompanyIndex::class)
            ->set('search', '99.888.777/0001-66')
            ->assertSee('Mercado Vbxzt SA');
    }

    /** A listagem de empresas pagina de 10 em 10. */
    public function test_listagem_de_empresas_pagina_de_10_em_10(): void
    {
        $this->actingAdmin();

        for ($i = 0; $i < 11; $i++) {
            Company::create([
                'corporate_name' => sprintf('Empresa Paginacao %02d', $i),
                'cnpj_cpf' => sprintf('1122233300%04d', $i),
            ]);
        }

        $companies = Livewire::test(CompanyIndex::class)
            ->viewData('companies');

        $this->assertSame(10, $companies->perPage());
        $this->assertSame(10, $companies->count());
    }
}
