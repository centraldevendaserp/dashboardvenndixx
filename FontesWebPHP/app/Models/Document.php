<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'cnpj_cpf',
        'ie',
        'model',
        'series',
        'number',
        'key',
        'month_year',
        'issue_dh',
        'path_xml',
        'protocol',
        'environment_type',
        'status_xml',
        'size',
        'vNF',
		'entrada',
    ];

    public function company()
    {
        return $this->hasOne(Company::class, 'cnpj_cpf', 'cnpj_cpf');
    }
}
