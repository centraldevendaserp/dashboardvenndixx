<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Nota descartada no sistema de vendas: existiu como venda, ganhou número e
 * nunca virou documento fiscal. Vem só do banco do ERP.
 *
 * ⚠️ Não confundir com DisableDocument, que é o evento de inutilização de FAIXA
 * homologado pela SEFAZ. Sem global scope: o escopo por empresa vem de fora,
 * sempre por whereIn('cnpj_cpf', ...).
 */
class DiscardedDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'cnpj_cpf', 'model', 'series', 'number', 'key', 'issue_dh',
        'month_year', 'value', 'situacao_erp', 'environment_type', 'identidade',
    ];

    public function company()
    {
        return $this->hasOne(Company::class, 'cnpj_cpf', 'cnpj_cpf');
    }
}
