<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisableDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'environment_type',
        'service',
        'uf',
        'year',
        'cnpj',
        'model',
        'series',
        'number_start',
        'number_end',
        'event_dh',
        'event_status',
        'protocol_number',
        'justification',
        'size',
        'path_xml',
    ];

    public function company()
    {
        return $this->hasOne(Company::class, 'cnpj_cpf', 'cnpj');
    }

}
