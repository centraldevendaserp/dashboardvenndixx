<?php

namespace Tests\Feature\Downloads;

use App\Support\DownloadCleanup;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * `storage/app/downloads` acumulava para sempre, e cada clique abortado deixava
 * centenas de MB permanentes — disco cheio derruba o portal E para a gravação
 * dos XMLs. Duas causas, as duas travadas aqui:
 *
 *  1. o purge só olhava `.zip`, mas o libzip monta num temporário `.part` e
 *     requisição que morre no meio deixa esse arquivo;
 *  2. só uma das telas chamava o purge — os quatro geradores têm de coletar.
 */
class LimpezaDeDownloadsTest extends TestCase
{
    private string $pasta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pasta = storage_path('app/downloads-teste-' . getmypid());
        File::ensureDirectoryExists($this->pasta);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->pasta);
        parent::tearDown();
    }

    private function criar(string $nome, int $idadeEmSegundos): string
    {
        $caminho = "{$this->pasta}/{$nome}";
        File::put($caminho, 'x');
        touch($caminho, time() - $idadeEmSegundos);

        return $caminho;
    }

    /** O caso que vazou 1,2 GB. */
    public function test_remove_temporario_part_antigo(): void
    {
        $part = $this->criar('invoice-11-1783968964.zip.p3frs8.part', 7200);

        DownloadCleanup::limpar($this->pasta);

        $this->assertFileDoesNotExist($part);
    }

    public function test_remove_zip_antigo(): void
    {
        $zip = $this->criar('nfe-1-1783968964.zip', 7200);

        DownloadCleanup::limpar($this->pasta);

        $this->assertFileDoesNotExist($zip);
    }

    /**
     * Arquivo recente fica: enquanto o libzip escreve, o mtime acompanha, e é
     * isso que impede a limpeza de derrubar um lote concorrente.
     */
    public function test_mantem_arquivo_recente(): void
    {
        $zip  = $this->criar('nfe-1-9999999999.zip', 60);
        $part = $this->criar('invoice-2-9999999999.zip.abcdef.part', 60);

        DownloadCleanup::limpar($this->pasta);

        $this->assertFileExists($zip);
        $this->assertFileExists($part);
    }

    public function test_pasta_inexistente_nao_quebra(): void
    {
        DownloadCleanup::limpar($this->pasta . '/nao-existe');

        $this->assertTrue(true);
    }

    /** Os quatro geradores de ZIP têm de coletar — basta um não coletar para sobrar lixo. */
    public static function geradores(): array
    {
        return [
            ['app/Livewire/Panel/Documents/Index.php'],
            ['app/Livewire/Panel/Dashboard/Invoice.php'],
            ['app/Livewire/Panel/Dashboard/Event.php'],
            ['app/Livewire/Panel/Dashboard/Disable.php'],
        ];
    }

    /** @dataProvider geradores */
    public function test_todo_gerador_de_zip_coleta_antes(string $arquivo): void
    {
        $fonte = File::get(base_path($arquivo));

        $this->assertStringContainsString('new ZipArchive', $fonte, "{$arquivo} deixou de gerar zip?");
        $this->assertStringContainsString(
            'DownloadCleanup::limpar',
            $fonte,
            "{$arquivo} gera ZIP em app/downloads e não coleta o lixo — foi assim que 1,2 GB ficaram parados."
        );
    }
}
