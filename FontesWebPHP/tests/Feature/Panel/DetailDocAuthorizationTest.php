<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Dashboard\DetailDoc;
use App\Models\Company;
use App\Models\Document;
use App\Models\EventDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Autorização ao ver detalhes de documento: o find() é por id, sem global scope,
 * então quem autoriza é a Policy. Usuário não pode espiar documento de empresa
 * à qual não está vinculado.
 */
class DetailDocAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '11222333000181';

    private function makeDocument(string $cnpj): Document
    {
        return Document::create([
            'cnpj_cpf' => $cnpj,
            'ie' => 'ISENTO',
            'model' => 55,
            'series' => 1,
            'number' => 1,
            'key' => 'NFe' . $cnpj . uniqid(),
            'month_year' => '202401',
            'issue_dh' => '2024-01-15',
            'path_xml' => '<xml/>',
            'protocol' => '135240000000001',
            'environment_type' => '1',
            'status_xml' => '100',
            'vNF' => 150.75,
        ]);
    }

    public function test_admin_ve_detalhe_de_nota(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $this->actingAs($admin);

        $doc = $this->makeDocument(self::CNPJ);

        Livewire::test(DetailDoc::class, ['user' => $admin])
            ->call('eventDetailDoc', $doc->id, 'invoice')
            ->assertDispatched('eventOpenModal');
    }

    public function test_usuario_vinculado_ve_nota_da_sua_empresa(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);
        $this->actingAs($user);

        $company = Company::create([
            'corporate_name' => 'Empresa Vinculada LTDA',
            'cnpj_cpf' => self::CNPJ,
        ]);
        $user->companies()->attach($company->id);

        $doc = $this->makeDocument(self::CNPJ);

        Livewire::test(DetailDoc::class, ['user' => $user])
            ->call('eventDetailDoc', $doc->id, 'invoice')
            ->assertDispatched('eventOpenModal');
    }

    public function test_usuario_nao_vinculado_nao_ve_nota_de_outra_empresa(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);
        $this->actingAs($user);

        // Documento de uma empresa à qual o usuário NÃO está vinculado.
        $doc = $this->makeDocument(self::CNPJ);

        Livewire::test(DetailDoc::class, ['user' => $user])
            ->call('eventDetailDoc', $doc->id, 'invoice')
            ->assertNotDispatched('eventOpenModal')
            ->assertSet('details', []);
    }

    public function test_id_inexistente_nao_abre_modal(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $this->actingAs($admin);

        Livewire::test(DetailDoc::class, ['user' => $admin])
            ->call('eventDetailDoc', PHP_INT_MAX, 'invoice')
            ->assertNotDispatched('eventOpenModal')
            ->assertSet('details', []);
    }

    public function test_usuario_nao_vinculado_nao_ve_evento(): void
    {
        $user = User::factory()->create(['is_admin' => 'N']);
        $this->actingAs($user);

        $event = EventDocument::create([
            'cnpj' => self::CNPJ,
            'environment_type' => '1',
            'model' => '55',
            'nfe_key' => 'NFe' . self::CNPJ . uniqid(),
            'event_status' => '135',
        ]);

        Livewire::test(DetailDoc::class, ['user' => $user])
            ->call('eventDetailDoc', $event->id, 'event')
            ->assertNotDispatched('eventOpenModal');
    }

    public function test_admin_ve_detalhe_de_evento(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $this->actingAs($admin);

        $event = EventDocument::create([
            'cnpj' => self::CNPJ,
            'environment_type' => '1',
            'model' => '55',
            'nfe_key' => 'NFe' . self::CNPJ . uniqid(),
            'event_status' => '135',
        ]);

        Livewire::test(DetailDoc::class, ['user' => $admin])
            ->call('eventDetailDoc', $event->id, 'event')
            ->assertDispatched('eventOpenModal');
    }
}
