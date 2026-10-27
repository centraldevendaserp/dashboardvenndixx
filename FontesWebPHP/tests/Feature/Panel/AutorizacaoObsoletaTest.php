<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Company\Index as CompanyIndex;
use App\Livewire\Panel\User\Index as UserIndex;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Autorização obsoleta nas telas de gestão: o mount() roda UMA vez, e toda ação
 * seguinte chega por /livewire/update hidratando o snapshot. Permissão guardada
 * só no mount() vale para sempre naquela aba — admin rebaixado continuaria
 * apagando usuários e empresas.
 *
 * Os testes reproduzem esse caminho: monta como admin, rebaixa no banco e chama
 * o método na MESMA instância já montada.
 */
class AutorizacaoObsoletaTest extends TestCase
{
    use DatabaseTransactions;

    /** Rebaixa sem mass assignment — `is_admin` não está no `$fillable`. */
    private function rebaixa(User $user): void
    {
        DB::table('users')->where('id', $user->id)->update(['is_admin' => 'N']);
        auth()->setUser(User::find($user->id));

        $this->assertFalse(auth()->user()->isAdmin(), 'pré-condição: já não é admin');
    }

    public function test_admin_rebaixado_nao_apaga_usuario_pela_tela_ja_aberta(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $vitima = User::factory()->create(['is_admin' => 'N']);
        $this->actingAs($admin);

        $tela = Livewire::test(UserIndex::class);   // mount() autoriza aqui

        $this->rebaixa($admin);

        $tela->call('deleteUser', $vitima->id)->assertForbidden();
        $this->assertNotNull(User::find($vitima->id), 'a vítima foi apagada por quem não é mais admin');
    }

    public function test_admin_rebaixado_nao_apaga_empresa_pela_tela_ja_aberta(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $this->actingAs($admin);

        $empresa = Company::create([
            'cnpj_cpf' => '11222333000181',
            'corporate_name' => 'ALVO DA EXCLUSAO LTDA',
        ]);

        $tela = Livewire::test(CompanyIndex::class);

        $this->rebaixa($admin);

        $tela->call('deleteCompany', $empresa->id)->assertForbidden();
        $this->assertNotNull(Company::withoutGlobalScopes()->find($empresa->id), 'empresa apagada por não-admin');
    }

    /** O admin de verdade continua conseguindo — a correção não travou o caminho legítimo. */
    public function test_admin_continua_apagando(): void
    {
        $admin = User::factory()->create(['is_admin' => 'S']);
        $vitima = User::factory()->create(['is_admin' => 'N']);
        $this->actingAs($admin);

        Livewire::test(UserIndex::class)->call('deleteUser', $vitima->id);

        $this->assertNull(User::find($vitima->id), 'admin deveria conseguir excluir');
    }

    /** Id inexistente é 404 do findOrFail, não 500 por chamar método em null. */
    public function test_empresa_inexistente_nao_estoura_500(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => 'S']));

        Livewire::test(CompanyIndex::class)->call('deleteCompany', 999999999)->assertOk();
    }
}
