<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/obat';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // RateLimiter::for('api', function (Request $request) {
        //     return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        // });

        // $this->routes(function () {
        //     Route::middleware('web')
        //         ->group(base_path('routes/web.php'));
        // });
    }

    public function map()
    {
        $this->mapApiRoutes();
        $this->mapAdminRoutes();
    }

    protected function mapApiRoutes()
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->namespace)
            ->group(base_path('routes/api.php'));
    }


    protected function mapAdminRoutes()
    {
        Route::prefix('')
            ->middleware('web')
            ->namespace($this->namespace)
            ->group(base_path('routes/web.php'))
            ->group(base_path('routes/master/transaction.php'))
            ->group(base_path('routes/main/patient.php'))
            ->group(base_path('routes/main/visit.php'))
            ->group(base_path('routes/main/pharmacy.php'))
            ->group(base_path('routes/master/user.php'))
            ->group(base_path('routes/master/obat.php'));
    }
}
