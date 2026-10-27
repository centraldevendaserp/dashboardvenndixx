<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Panel\{
    Dashboard\Index as DashboardIndex,
    Documents\Index as DocumentsIndex,
    FiscalStatus\Index as FiscalStatusIndex,
    Company\Index as CompanyIndex,
    User\Index as UserIndex,
};

use App\Livewire\Auth\{
    Login,
    ForgotPassword,
    PasswordReset,
};

use App\Http\Controllers\{
    DocsController,
    EventsController,
    ReportController,
    InstallController,
};
use App\Http\Controllers\DocumentsDownloadController;
use App\Http\Controllers\DocumentsReportController;
use App\Http\Controllers\FiscalStatusReportController;

// Instalador web: sem a tranca `storage/installed`, a raiz cai no /install;
// depois de instalado, os dois levam ao login.
Route::get('/install', [InstallController::class, 'show'])->name('install.show');
Route::post('/install', [InstallController::class, 'store'])->name('install.store');

// ⚠️ Sem Closure de propósito: `route:cache` não a serializa, e uma só derruba
// o comando inteiro no deploy.
Route::get('/', [InstallController::class, 'raiz'])->name('raiz');

Route::group(['prefix' => 'auth', 'as' => 'auth.', 'middleware' => ['auth.redirect']], function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/forgot-password', ForgotPassword::class)->name('forgot.password');
    Route::get('/password-reset/{token}', PasswordReset::class)->name('password.reset');
});

Route::group(['prefix' => 'panel', 'as' => 'panel.', 'middleware' => ['auth.web']], function () {

    Route::group(['prefix' => 'dashboard', 'as' => 'dashboard.'], function () {
        Route::get('/', DashboardIndex::class)->name('index');
    });

    Route::group(['prefix' => 'documents', 'as' => 'documents.'], function () {
        Route::get('/downloads/{file}', DocumentsDownloadController::class)
            ->where('file', '[a-z0-9\-]+\.zip')->name('download');
        // throttle: o relatório faz count() + get() de até 15.000 linhas e um
        // GROUP BY sobre a tabela inteira. 60/min está muito acima do uso humano
        // e corta o laço automatizado.
        Route::get('/{type}/report', DocumentsReportController::class)
            ->middleware('throttle:60,1')->name('report');
        Route::get('/{type}', DocumentsIndex::class)->name('type');
    });

    Route::group(['prefix' => 'fiscal-status', 'as' => 'fiscal_status.'], function () {
        Route::get('/report', FiscalStatusReportController::class)
            ->middleware('throttle:60,1')->name('report');
        Route::get('/', FiscalStatusIndex::class)->name('index');
    });

    Route::group(['prefix' => 'companies', 'as' => 'companies.'], function () {
        Route::get('/', CompanyIndex::class)->name('index');
    });

    Route::group(['prefix' => 'users', 'as' => 'users.'], function () {
        Route::get('/', UserIndex::class)->name('index');
    });

    Route::group(['prefix' => 'docs', 'as' => 'docs.'], function () {
        Route::get('/{id}/print-invoice', [DocsController::class, 'printInvoice'])->name('print.invoice')->middleware('access.invoice');
        Route::get('/{id}/print-event-nfenfce', [EventsController::class, 'printEvent_nfenfce'])->name('print.event.nfenfce');
        Route::get('/{id}/print-event-cte', [DocsController::class, 'printEvent_cte'])->name('print.event.cte');
    });

    Route::group(['prefix' => 'reports', 'as' => 'reports.'], function () {
        Route::get('/invoices', [ReportController::class, 'invoices'])->name('invoices');
        Route::get('/events', [ReportController::class, 'events'])->name('events');
        Route::get('/disables', [ReportController::class, 'disables'])->name('disables');
    });
});
