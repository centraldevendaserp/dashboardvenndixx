<?php

namespace App\Policies;

use App\Models\NfseDocument;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Autorização da NFS-e: admin vê tudo; usuário comum só as notas cujo PRESTADOR
 * (cnpj_prestador) é uma empresa vinculada a ele. Ability: access-nfse
 * (Gate::inspect('access-nfse', $nfse) → método accessNfse via Str::camel).
 */
class NfseDocumentPolicy
{
    use HandlesAuthorization;

    public function accessNfse(User $user, NfseDocument $document)
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->companies->where('cnpj_cpf', '===', $document->cnpj_prestador)->first() !== null;
    }
}
