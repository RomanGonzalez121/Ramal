<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Solo los operadores entran al panel de control.
        Gate::define('operar', fn (User $usuario) => $usuario->esOperador());

        // La API pública v1 limita por token (no por IP): cada token tiene su propio cupo por minuto.
        RateLimiter::for('api-v1', fn (Request $request) => Limit::perMinute((int) config('ramal.api.limite_por_minuto'))
            ->by($request->user()?->currentAccessToken()?->id ?? $request->ip())
            ->response(fn (Request $request, array $cabeceras) => response()->json([
                'mensaje' => 'Pediste demasiado rápido. Esperá unos segundos y volvé a intentar.',
                'reintentar_en_s' => (int) ($cabeceras['Retry-After'] ?? 60),
            ], 429, $cabeceras)));
    }
}
