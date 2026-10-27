<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'environment_type',
        'cnpj',
        'model',
        'nfe_key',
        'event_dh',
        'event_type',
        'event_number',
        'event_desc',
        'event_status',
        'protocol_number',
        'justification',
        'correction',
        'size',
        'path_xml',
    ];

    public function company()
    {
        return $this->hasOne(Company::class, 'cnpj_cpf', 'cnpj');
    }
}
