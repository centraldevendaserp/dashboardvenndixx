<?php

namespace App\Support;

use App\Livewire\Panel\Documents\Index as DocumentsIndex;

/**
 * Regras do filtro de status do dashboard (/panel/dashboard).
 *
 * A tela mostra três fontes sob UM filtro só, e cada uma tem seu domínio:
 *   documents.status_xml           100/101/110/135/205/301/302/303/132/103/104/105
 *   event_documents.event_status   135 (evento vinculado)
 *   disable_documents.event_status 102 (inutilização)
 *
 * Cada fonte recorta só pelos códigos do SEU domínio; interseção vazia = o
 * filtro não se aplica àquela fonte (ela não é zerada). O vocabulário vem de
 * `Documents\Index::statusGroups()`, para dashboard e Documentos não divergirem.
 */
class DashboardStatusScope
{
    /** Grupos oferecidos no filtro do dashboard, na ordem do select. */
    public const GRUPOS = ['autorizada', 'cancelada', 'denegada', 'inutilizada'];

    /** Exclusivo das inutilizações: cStat 102 não descreve nota nenhuma. */
    public const DOMINIO_INUTILIZACOES = [102];

    /** Evento registrado/vinculado (cancelamento, CC-e...). */
    public const DOMINIO_EVENTOS = [135];

    /**
     * Códigos que descrevem uma NOTA: o vocabulário conhecido menos o que é
     * exclusivo de inutilização. Derivado de knownCodes() para acompanhar
     * sozinho quem mexer nos grupos.
     */
    public static function dominioNotas(): array
    {
        return array_values(array_diff(
            DocumentsIndex::knownCodes(),
            self::DOMINIO_INUTILIZACOES
        ));
    }

    /**
     * Aba do bloco "Documentos" que o filtro deve abrir. Só decide com UM grupo
     * marcado; com mais de um (ou nenhum) cai na lista geral. "Denegada" não tem
     * aba própria.
     */
    public static function abaPara($grupos): ?string
    {
        $abas = [
            'inutilizada' => 'disable',
            'cancelada'   => 'event',
            'autorizada'  => 'authorized',
        ];

        $grupos = array_values((array) $grupos);

        if (count($grupos) !== 1) {
            return 'invoice';
        }

        return $abas[$grupos[0]] ?? 'invoice';
    }

    /**
     * As inutilizações entram como lista secundária no relatório/zip das notas?
     * Regra única do dashboard e da tela Documentos:
     *
     *   nenhum grupo (= todos) ......... traz
     *   'inutilizada' junto com outros . traz
     *   'inutilizada' sozinha .......... não (já é a tabela principal)
     *   não pediu 'inutilizada' ........ não
     *
     * Recebe GRUPOS, não cStat: 'rejeitada' é catch-all sem código e viraria []
     * — indistinguível de "todos".
     */
    public static function incluiInutilizacoes($grupos): bool
    {
        $grupos = array_values((array) $grupos);

        if (empty($grupos)) {
            return true;
        }

        if (! in_array('inutilizada', $grupos, true)) {
            return false;
        }

        return $grupos !== ['inutilizada'];
    }

    /** Rótulos do select: ['autorizada' => 'Autorizada', ...]. */
    public static function opcoes(): array
    {
        $grupos = DocumentsIndex::statusGroups();
        $opcoes = [];

        foreach (self::GRUPOS as $chave) {
            if (isset($grupos[$chave])) {
                $opcoes[$chave] = $grupos[$chave]['label'];
            }
        }

        return $opcoes;
    }

    /**
     * Chaves de grupo -> códigos cStat. Widgets e relatório trabalham com cStat,
     * então o filtro despacha código; valor já numérico é preservado.
     */
    public static function expandir($chaves): array
    {
        $grupos = DocumentsIndex::statusGroups();
        $codes = [];

        foreach ((array) $chaves as $chave) {
            if (is_numeric($chave)) {
                $codes[] = (int) $chave;
                continue;
            }

            foreach ($grupos[$chave]['codes'] ?? [] as $code) {
                $codes[] = (int) $code;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * cStat -> chaves de grupo (inverso de expandir). Só devolve grupos do
     * vocabulário do dashboard; código de fora (rejeição, encerrado) não vira
     * grupo, porque não há chip para ele aqui.
     */
    public static function grupos($codes): array
    {
        $grupos = DocumentsIndex::statusGroups();
        $codes = array_map('intval', (array) $codes);
        $achados = [];

        foreach (self::GRUPOS as $chave) {
            $doGrupo = array_map('intval', $grupos[$chave]['codes'] ?? []);

            if (array_intersect($codes, $doGrupo)) {
                $achados[] = $chave;
            }
        }

        return $achados;
    }

    /** Códigos aplicáveis a `documents.status_xml` (vazio = não recorta). */
    public static function paraNotas($codes): array
    {
        return self::intersectar($codes, self::dominioNotas());
    }

    /** Códigos aplicáveis a `event_documents.event_status` (vazio = não recorta). */
    public static function paraEventos($codes): array
    {
        return self::intersectar($codes, self::DOMINIO_EVENTOS);
    }

    /** Códigos aplicáveis a `disable_documents.event_status` (vazio = não recorta). */
    public static function paraInutilizacoes($codes): array
    {
        return self::intersectar($codes, self::DOMINIO_INUTILIZACOES);
    }

    private static function intersectar($codes, array $dominio): array
    {
        $codes = array_map('intval', (array) $codes);

        return array_values(array_intersect($codes, $dominio));
    }
}
