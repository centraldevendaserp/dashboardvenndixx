<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
        \App\Models\Document::class => \App\Policies\DocumentPolicy::class,
        \App\Models\EventDocument::class => \App\Policies\EventDocumentPolicy::class,
        \App\Models\DisableDocument::class => \App\Policies\DisableDocumentPolicy::class,
        \App\Models\NfseDocument::class => \App\Policies\NfseDocumentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
