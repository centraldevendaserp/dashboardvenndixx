<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Ledger do último status conhecido por CHAVE (NF-e, NFC-e, CT-e, CT-e OS,
 * MDF-e). Escrito pelos canais de status (XML e ERP) e lido pela aba
 * "Status SEFAZ". Inclui notas que nunca viraram documento.
 */
class FiscalStatus extends Model
{
    protected $table = 'fiscal_status';

    protected $fillable = [
        'key', 'model', 'cnpj_emit', 'series', 'number', 'cstat', 'category',
        'x_motivo', 'n_prot', 'dh_recbto', 'environment_type', 'source',
    ];

    protected $casts = [
        'dh_recbto' => 'datetime',
    ];

    public const CATEGORIES = [
        'autorizada'  => 'Autorizada',
        'cancelada'   => 'Cancelada',
        'denegada'    => 'Denegada',
        'inutilizada' => 'Inutilizada',
        'encerrado'   => 'Encerrado',
        'rejeitada'   => 'Rejeitada',
        'duplicidade' => 'Duplicidade',
    ];

    /**
     * Mapa cStat -> categoria, alinhado aos statusGroups() do Documents\Index.
     * Catch-all = rejeitada.
     */
    public static function categoryFor(int $cstat): string
    {
        return match (true) {
            in_array($cstat, [100, 150], true)                => 'autorizada',
            in_array($cstat, [101, 135, 151, 155], true)      => 'cancelada',
            in_array($cstat, [110, 205, 301, 302, 303], true) => 'denegada',
            $cstat === 102                                    => 'inutilizada',
            $cstat === 132                                    => 'encerrado',
            default                                           => 'rejeitada',
        };
    }

    /** Categoria de rejeição (catch-all do categoryFor). */
    public const CATEGORY_REJEITADA = 'rejeitada';

    /**
     * Duplicidade: a nota foi recusada porque número/chave já existe na SEFAZ.
     * Só o ERP a nomeia; pelo canal XML ela cai no catch-all "rejeitada".
     *
     * ⚠️ Fora do scopeRejeitadaOuContingencia de propósito: o universo da aba
     * "Status SEFAZ" é rejeitada ∪ contingência, e a soma dos chips tem de bater
     * com o total. A duplicidade aparece na aba NFC-e.
     */
    public const CATEGORY_DUPLICIDADE = 'duplicidade';

    /**
     * cStat "não consta na base da SEFAZ". Não é recusa, é ausência: numa nota
     * emitida em contingência significa "ainda está em contingência".
     */
    public const CSTAT_NAO_CONSTA = 217;

    /** Situação própria da aba — derivada na leitura, não vem do categoryFor. */
    public const CATEGORY_CONTINGENCIA = 'contingencia';

    /** tpEmis de emissão normal; qualquer outro valor é contingência. */
    public const TP_EMIS_NORMAL = '1';

    /** tpEmis = dígito 35 da chave. Qualificado para não colidir em join/subselect. */
    public const SQL_TP_EMIS = 'SUBSTRING(`fiscal_status`.`key`, 35, 1)';

    /**
     * Rótulo do tipo de contingência POR MODELO: o mesmo tpEmis significa coisas
     * diferentes (2 é FS-IA na NF-e e "Contingência" no MDF-e).
     */
    public const TP_EMIS_LABELS = [
        55 => [2 => 'FS-IA', 3 => 'SCAN', 4 => 'EPEC', 5 => 'FS-DA', 6 => 'SVC-AN', 7 => 'SVC-RS', 9 => 'Offline'],
        65 => [2 => 'FS-IA', 3 => 'SCAN', 4 => 'EPEC', 5 => 'FS-DA', 6 => 'SVC-AN', 7 => 'SVC-RS', 9 => 'Offline'],
        57 => [4 => 'EPEC', 5 => 'FS-DA', 7 => 'SVC-RS', 8 => 'SVC-SP'],
        67 => [4 => 'EPEC', 5 => 'FS-DA', 7 => 'SVC-RS', 8 => 'SVC-SP'],
        58 => [2 => 'Contingência'],
    ];

    /** tpEmis da chave (dígito 35; substr é 0-based). '' se a chave for curta. */
    public function getTpEmisAttribute(): string
    {
        return substr((string) $this->key, 34, 1);
    }

    /**
     * FOI emitida em contingência (tpEmis != 1) — fato permanente da chave.
     * Diferente de estaEmContingencia(), que é a SITUAÇÃO: nota emitida em
     * contingência e já autorizada é "foi" true e "está" false.
     */
    public function isContingencia(): bool
    {
        $tp = $this->tp_emis;

        return $tp !== '' && $tp !== self::TP_EMIS_NORMAL;
    }

    /** Rótulo do tipo de contingência (tooltip); null se emissão normal. */
    public function contingenciaLabel(): ?string
    {
        if (! $this->isContingencia()) {
            return null;
        }

        $tp = (int) $this->tp_emis;

        return self::TP_EMIS_LABELS[(int) $this->model][$tp] ?? "tpEmis {$tp}";
    }

    /**
     * ESTÁ em contingência: emitida em contingência E a SEFAZ ainda não tem a
     * nota (217). Transmitida, ela vira autorizada ou rejeitada e sai daqui.
     */
    public function estaEmContingencia(): bool
    {
        if ($this->category === self::CATEGORY_CONTINGENCIA) {
            return true;
        }

        // A inferência tpEmis+217 só vale para o canal XML: a linha do ERP tem
        // categoria direta.
        if ($this->source === 'erp'
            || ! $this->isContingencia()
            || (int) $this->cstat !== self::CSTAT_NAO_CONSTA) {
            return false;
        }

        return ! $this->foiSubstituidaPorAutorizada();
    }

    /**
     * A chave de contingência foi substituída por uma emissão normal?
     *
     * Quando a comunicação volta, o emissor reemite a mesma nota em modo normal:
     * mesmo número e série, chave diferente (o tpEmis é o dígito 35). A antiga
     * fica órfã e responde 217 para sempre — sem esta checagem o portal anuncia
     * pendência numa nota que já está autorizada.
     */
    protected function foiSubstituidaPorAutorizada(): bool
    {
        if (blank($this->cnpj_emit) || blank($this->number)) {
            return false;
        }

        return DB::table('documents')
            ->where('cnpj_cpf', $this->cnpj_emit)
            ->where('model', $this->model)
            ->where('series', $this->series)
            ->where('number', $this->number)
            ->where('key', '<>', (string) $this->key)
            ->where('status_xml', 100)
            ->exists();
    }

    /**
     * Data/hora para a tela; sem hora, mostra só a data. O ERP manda a emissão e
     * a hora às vezes vem nula: "00:00" seria lido como erro de fuso.
     */
    public function dataHoraLegivel(): string
    {
        if ($this->dh_recbto === null) {
            return '—';
        }

        return $this->dh_recbto->format($this->dh_recbto->format('H:i:s') === '00:00:00' ? 'd/m/Y' : 'd/m/Y H:i');
    }

    /**
     * A situação a MOSTRAR. Contingência ganha do catch-all "rejeitada", porque
     * o 217 não é recusa: a coluna `category` guarda o que o cStat disse e a
     * contingência é derivada aqui, na leitura.
     */
    public function categoriaEfetiva(): string
    {
        return $this->estaEmContingencia()
            ? self::CATEGORY_CONTINGENCIA
            : (string) $this->category;
    }

    /**
     * Rejeitada = a SEFAZ recebeu e recusou; exclui contingência, que não é
     * recusa. O OR fica encapsulado na closure para não vazar do escopo.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeRejeitada($query)
    {
        return $query->where('category', self::CATEGORY_REJEITADA)
            ->where(fn ($w) => $w
                ->where('source', 'erp')   // ERP tem categoria direta, sem inferência
                ->orWhereRaw(self::SQL_TP_EMIS . ' = ?', [self::TP_EMIS_NORMAL])
                ->orWhere('cstat', '<>', self::CSTAT_NAO_CONSTA));
    }

    /**
     * Versão SQL do estaEmContingencia(). ⚠️ Mexeu num, mexa no outro.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeEmContingencia($query)
    {
        return $query->where(fn ($q) => $q
            ->where('category', self::CATEGORY_CONTINGENCIA)   // ERP: gravada
            ->orWhere(fn ($w) => $w
                ->where('source', '<>', 'erp')                 // inferência: só canal XML
                ->whereRaw(self::SQL_TP_EMIS . ' <> ?', [self::TP_EMIS_NORMAL])
                ->where('cstat', self::CSTAT_NAO_CONSTA)));
    }

    /**
     * Universo da aba: a união EXATA dos dois chips. Mais largo que isso cria
     * linha órfã, visível em "Todas" e em chip nenhum. Cada braço na sua
     * closure, para os OR não vazarem o escopo por empresa.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeRejeitadaOuContingencia($query)
    {
        return $query->where(fn ($q) => $q
            ->where(fn ($w) => $w->rejeitada())
            ->orWhere(fn ($w) => $w->emContingencia()));
    }
}
