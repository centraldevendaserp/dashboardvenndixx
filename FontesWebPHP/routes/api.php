<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckSystem;

use App\Http\Controllers\{
    DocsController,
    EventsController,
    ErpDiscardController,
    ErpNfseController,
    ErpStatusController,
    StatusController,
};

Route::group(['prefix' => 'docs', 'as' => 'docs.'], function () {
    Route::post('/nfenfce/upload', [DocsController::class, 'nfe_nfce'])->name('nfe_nfce')->middleware(CheckSystem::class);
	Route::post('/nfe/upload', [DocsController::class, 'nfe'])->name('nfe')->middleware(CheckSystem::class);
    Route::post('/sat/upload', [DocsController::class, 'sat'])->name('sat')->middleware(CheckSystem::class);
    Route::post('/cte/upload', [DocsController::class, 'cte'])->name('cte')->middleware(CheckSystem::class);
    Route::post('/mdfe/upload', [DocsController::class, 'mdfe'])->name('mdfe')->middleware(CheckSystem::class);
    Route::post('/nfse/upload', [DocsController::class, 'nfse'])->name('nfse')->middleware(CheckSystem::class);
    Route::post('/eventos/nfenfce/upload', [EventsController::class, 'cancelamento_cce'])->name('cancelamento_cce')->middleware(CheckSystem::class);
    Route::post('/eventos/cte/upload', [EventsController::class, 'cancelamento_cce_cte'])->name('cancelamento_cce_cte')->middleware(CheckSystem::class);
    Route::post('/eventos/mdfe/upload', [EventsController::class, 'eventos_mdfe'])->name('eventos_mdfe')->middleware(CheckSystem::class);
    Route::post('/inutilizacao/nfenfce/upload', [EventsController::class, 'inutilizacao_nfenfce'])->name('inutilizacao_nfenfce')->middleware(CheckSystem::class);
    Route::post('/inutilizacao/cte/upload', [EventsController::class, 'inutilizacao_cte'])->name('inutilizacao_cte')->middleware(CheckSystem::class);
    Route::post('/status/upload', [StatusController::class, 'upload'])->name('status')->middleware(CheckSystem::class);
    Route::post('/status-erp/upload', [ErpStatusController::class, 'upload'])->name('status_erp')->middleware(CheckSystem::class);
    Route::post('/nfse-erp/upload', [ErpNfseController::class, 'upload'])->name('nfse_erp')->middleware(CheckSystem::class);
    Route::post('/descartes-erp/upload', [ErpDiscardController::class, 'upload'])->name('descartes_erp')->middleware(CheckSystem::class);
});
