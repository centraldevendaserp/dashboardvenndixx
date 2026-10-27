<?php

namespace App\Http\Controllers;

use App\Livewire\Panel\Documents\Index as DocumentsIndex;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Entrega os zips do "Baixar XML" (telas de Documentos e dashboard).
 *
 * Fica fora do ciclo Livewire de propósito: o Livewire bufferiza o arquivo
 * inteiro em base64 e estoura a memória em zip grande; aqui é streaming direto
 * do disco. O nome é validado por regex e o zip só desce para quem o gerou.
 */
class DocumentsDownloadController extends Controller
{
    public function __invoke(string $file)
    {
        abort_unless(preg_match('/^([a-z]+)-(\d+)-(\d+)\.zip$/', $file, $m) === 1, 404);
        abort_unless((int) $m[2] === (int) auth('web')->id(), 403);

        $path = storage_path("app/downloads/{$file}");

        abort_unless(File::exists($path), 404);

        return response()
            ->download($path, $this->downloadName($m[1], (int) $m[3]), ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    /**
     * Nome amigável do download: "{Tipo} - {dd-mm-aaaa}.zip". A data sai do
     * timestamp do nome interno, que não muda — é ele que carrega o dono do
     * arquivo e a validação da rota.
     */
    private function downloadName(string $prefix, int $timestamp): string
    {
        $doDashboard = [
            'invoice'  => 'Todos',
            'event'    => 'Eventos',
            'disabled' => 'Inutilizadas',
        ];

        $label = $doDashboard[$prefix]
            ?? (DocumentsIndex::types()[$prefix]['label'] ?? ucfirst($prefix));

        // Sem o sufixo "-e" no nome: NF-e -> NF, NFC-e -> NFC, etc.
        $label = preg_replace('/-e$/i', '', $label);

        $date = Carbon::createFromTimestamp($timestamp, config('app.timezone'))->format('d-m-Y');

        // Rótulos com barra (ex.: Entrada/Compras) não podem virar subpasta no nome.
        return str_replace(['/', '\\'], '-', "{$label} - {$date}.zip");
    }
}
