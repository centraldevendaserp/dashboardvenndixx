<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * NFS-e (nota fiscal de serviço). Tabela própria (ver migration create_nfse_documents):
 * status TEXTUAL, chave opcional, e dedup pela coluna derivada `identidade`.
 * A empresa é o PRESTADOR (cnpj_prestador).
 */
class NfseDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'padrao',
        'cnpj_prestador',
        'ie',
        'cnpj_cpf_tomador',
        'municipio',
        'numero',
        'serie',
        'cod_verificacao',
        'chave',
        'identidade',
        'month_year',
        'issue_dh',
        'situacao',
        'protocol',
        'environment_type',
        'valor',
        'size',
        'path_xml',
    ];

    public function company()
    {
        return $this->hasOne(Company::class, 'cnpj_cpf', 'cnpj_prestador');
    }
}
