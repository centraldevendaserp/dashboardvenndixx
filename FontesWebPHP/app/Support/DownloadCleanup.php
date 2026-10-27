<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Coleta o lixo de `storage/app/downloads` (área de estágio do "Baixar XML").
 * Chame nos 4 geradores de zip — se um deles não coletar, sobra lixo lá.
 *
 * ⚠️ A régua é IDADE, não extensão. O libzip monta num temporário
 * `<nome>.zip.<6 chars>.part` e só renomeia no fim; requisição que morre no meio
 * deixa o `.part`, que um filtro por `.zip` nunca apagaria.
 */
final class DownloadCleanup
{
    /** Acima disto, o arquivo é abandono — não trabalho em andamento. */
    public const VALIDADE = 3600;

    /**
     * Enquanto o libzip escreve, o mtime do `.part` acompanha — é o que faz um
     * lote concorrente sobreviver a esta limpeza sem trava.
     */
    public static function limpar(string $pasta): void
    {
        if (! File::isDirectory($pasta)) {
            return;
        }

        $corte = time() - self::VALIDADE;

        foreach (File::files($pasta) as $arquivo) {
            if ($arquivo->getMTime() < $corte) {
                File::delete($arquivo->getPathname());
            }
        }
    }
}
