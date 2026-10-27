<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Nenhuma rota pode ter Closure como action: o `route:cache` não a serializa, e
 * uma só derruba o comando inteiro — o portal roda sem cache de rotas e o aviso
 * se perde no meio do deploy. Quebrar aqui é melhor que quebrar lá.
 */
class RotasSemClosureTest extends TestCase
{
    public function test_nenhuma_rota_usa_closure(): void
    {
        $comClosure = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($rota) => $rota->getAction('uses') instanceof \Closure)
            ->map(fn ($rota) => $rota->methods()[0] . ' /' . $rota->uri())
            ->values()
            ->all();

        $this->assertSame(
            [],
            $comClosure,
            "Rota com Closure impede o route:cache do deploy:\n  " . implode("\n  ", $comClosure)
        );
    }

    /** A raiz continua levando para onde deve. */
    public function test_raiz_redireciona(): void
    {
        $this->get('/')->assertRedirect(route('auth.login'));
    }
}
