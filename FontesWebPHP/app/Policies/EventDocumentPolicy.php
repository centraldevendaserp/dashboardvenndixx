<?php

namespace App\Policies;

use App\Models\EventDocument;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EventDocumentPolicy
{
    use HandlesAuthorization;

    public function accessEventDocument(User $user, EventDocument $document)
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->companies->where('cnpj_cpf', '===', $document->cnpj)->first() !== null;
    }
}
