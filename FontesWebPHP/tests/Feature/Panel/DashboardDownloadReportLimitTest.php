<?php

namespace Tests\Feature\Panel;

use App\Livewire\Panel\Dashboard\Invoice as DashboardInvoice;
use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Teto de 15 mil no download/relatório do dashboard, o mesmo das telas de
 * Documentos: no volume de produção o zip e o relatório estouram a memória.
 * Acima do teto, avisa; dentro dele, baixa pela rota direta.
 */
class DashboardDownloadReportLimitTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('is_admin', 'S')->firstOrFail();
    }

    public function test_relatorio_de_notas_do_dashboard_limita_e_avisa(): void
    {
        $this->actingAs($this->admin());

        // Sem filtro na sessão -> todos os documentos do dump (> 15 mil).
        session(['searchArgsToDocReport' => null]);

        $this->get(route('panel.reports.invoices'))
            ->assertOk()
            ->assertSee('Mostrando os primeiros 15.000');
    }

    public function test_zip_do_dashboard_acima_do_limite_avisa_e_nao_baixa(): void
    {
        $user = $this->admin();
        $this->actingAs($user);

        // NFC-e do dump inteiro (~130 mil) via filtro de busca do dashboard.
        Livewire::test(DashboardInvoice::class, ['user' => $user])
            ->call('eventDocsSearch', ['doc_types' => [65]])
            ->call('eventDownloadCompressedDoc')
            ->assertDispatched('eventCuteToast')
            ->assertDispatched('zip-pronto')
            ->assertNoRedirect();
    }

    public function test_zip_pequeno_do_dashboard_baixa_pela_rota_direta(): void
    {
        $user = $this->admin();
        $this->actingAs($user);

        $dir = storage_path('app/tmp');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }
        File::put("{$dir}/test.xml", '<xml>teste</xml>');

        // Nota isolada em 2099 (issue_dh é DATE) com XML físico presente.
        $cnpj = Company::query()->value('cnpj_cpf');
        Document::create([
            'cnpj_cpf'         => $cnpj,
            'ie'               => '000',
            'model'            => 55,
            'series'           => 1,
            'number'           => 990001,
            'key'              => str_pad('DASH2099A', 44, '0'),
            'month_year'       => '209901',
            'issue_dh'         => '2099-01-05',
            'path_xml'         => '/tmp/test.xml',
            'protocol'         => 'proto-dash1',
            'environment_type' => '1',
            'status_xml'       => '100',
            'vNF'              => 10.0,
        ]);

        $component = Livewire::test(DashboardInvoice::class, ['user' => $user])
            ->call('eventDocsSearch', ['first_date' => '2099-01-01', 'last_date' => '2099-12-31'])
            ->call('eventDownloadCompressedDoc')
            ->assertDispatched('zip-pronto')
            ->assertRedirectContains('/panel/documents/downloads/');

        // Nome amigável: o dashboard baixa "Todos - {dd-mm-aaaa}.zip".
        $response = $this->get($component->effects['redirect']);
        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/Todos - \d{2}-\d{2}-\d{4}\.zip/',
            (string) $response->headers->get('content-disposition')
        );
    }
}
