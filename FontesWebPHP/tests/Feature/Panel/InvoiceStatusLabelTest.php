<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Dashboard\Invoice;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O badge de status da lista do dashboard não pode deixar célula em branco para
 * status desconhecido: o rótulo sai do Documents\Index::groupForCode(), então
 * código fora dos conhecidos vira "Rejeitada".
 *
 * Isola os dados numa data futura, fora do dump.
 */
class InvoiceStatusLabelTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    private function insereDoc(string $cnpj, string $status, int $number, string $key): void
    {
        DB::table('documents')->insert([
            'cnpj_emit' => $cnpj, 'cnpj_cpf' => $cnpj, 'ie' => '1', 'model' => 55,
            'series' => 1, 'number' => $number, 'key' => $key, 'month_year' => '202703',
            'issue_dh' => '2027-03-15', 'path_xml' => '/x', 'protocol' => (string) $number,
            'environment_type' => '1', 'status_xml' => $status, 'vNF' => 10,
        ]);
    }

    public function test_status_desconhecido_vira_rejeitada_e_conhecido_continua_certo(): void
    {
        $this->actingAs($admin = $this->admin());

        // Empresa real do dump (o admin enxerga todas via global scope linked_user).
        $cnpj = Company::query()->value('cnpj_cpf');
        $this->assertNotNull($cnpj, 'O dump precisa ter ao menos uma empresa cadastrada.');

        $this->insereDoc($cnpj, '100', 990001, '4227' . $cnpj . '55001990001990011'); // Autorizada
        $this->insereDoc($cnpj, '217', 990002, '4227' . $cnpj . '55001990002990022'); // rejeição (V2-4)

        Livewire::test(Invoice::class, ['user' => $admin])
            ->call('eventDocsSearch', ['first_date' => '2027-03-15', 'last_date' => '2027-03-15'])
            ->assertOk()
            ->assertSeeHtml('Autorizada')  // 100 — comportamento existente preservado
            ->assertSeeHtml('Rejeitada');  // 217 — antes: célula EM BRANCO (o bug V2-4)
    }
}
