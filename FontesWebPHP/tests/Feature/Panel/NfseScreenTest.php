<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Documents\Index as DocumentsIndex;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tela por tipo da NFS-e: consulta `nfse_documents`, mostra a situação TEXTUAL e
 * filtra pelos chips nfse_*. Isola os dados numa data futura.
 */
class NfseScreenTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    private function insereNfse(string $cnpj, string $numero, string $situacao, string $identidade): void
    {
        DB::table('nfse_documents')->insert([
            'padrao' => 'municipal', 'cnpj_prestador' => $cnpj, 'municipio' => '4205407',
            'numero' => $numero, 'cod_verificacao' => 'V' . $numero, 'identidade' => $identidade,
            'month_year' => '202704', 'issue_dh' => '2027-04-10', 'situacao' => $situacao,
            'environment_type' => '1', 'valor' => 123.45, 'path_xml' => '/x',
        ]);
    }

    public function test_aba_nfse_renderiza_e_lista_dados(): void
    {
        $this->actingAs($this->admin());
        $cnpj = Company::query()->value('cnpj_cpf');

        $this->insereNfse($cnpj, '770001', 'Autorizada', 'nfse-scr-a');
        $this->insereNfse($cnpj, '770002', 'Cancelada', 'nfse-scr-b');

        Livewire::test(DocumentsIndex::class, ['type' => 'nfse'])
            ->set('first_date', '2027-04-01')
            ->set('last_date', '2027-04-30')
            ->assertOk()
            ->assertSee('770001')
            ->assertSee('770002')
            ->assertSee('Autorizada')
            ->assertSee('Cancelada');
    }

    public function test_filtro_textual_nfse_cancelada(): void
    {
        $this->actingAs($this->admin());
        $cnpj = Company::query()->value('cnpj_cpf');

        $this->insereNfse($cnpj, '771001', 'Autorizada', 'nfse-flt-a');
        $this->insereNfse($cnpj, '771002', 'Cancelada', 'nfse-flt-b');

        Livewire::test(DocumentsIndex::class, ['type' => 'nfse'])
            ->set('first_date', '2027-04-01')
            ->set('last_date', '2027-04-30')
            ->call('toggleStatus', 'nfse_cancelada')
            ->assertSee('771002')
            ->assertDontSee('771001');
    }
}
