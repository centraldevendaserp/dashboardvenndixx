<?php

namespace App\Policies;

use App\Models\DisableDocument;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DisableDocumentPolicy
{
    use HandlesAuthorization;

    public function accessDisableDocument(User $user, DisableDocument $document)
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->companies->where('cnpj_cpf', '===', $document->cnpj)->first() !== null;
    }
}
