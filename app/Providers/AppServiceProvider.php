<?php

namespace App\Providers;

use App\Exceptions\ApiExceptionHandler;
use Illuminate\Support\ServiceProvider;
use App\Services\HttpClientService;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerExceptionHandler();
        $this->registerTelescope();
        $this->registerHttpClientService();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register the exception handler - extends the Dingo one
     *
     * @return void
     */
    protected function registerExceptionHandler(): void
    {
        $this->app->singleton('api.exception', function ($app) {
            $config = $app->config->get('api');
            return new ApiExceptionHandler($app['Illuminate\Contracts\Debug\ExceptionHandler'], $config['errorFormat'], $config['debug']);
        });
    }

    /**
     * Conditionally register the telescope service provider
     */
    protected function registerTelescope(): void
    {
        if ($this->app->environment('local', 'testing')) {
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Register HttpClientService for API calls
     *
     * @return void
     */
    protected function registerHttpClientService(): void
    {
        $this->app->singleton(HttpClientService::class, function ($app) {
            return new HttpClientService();
        });

        $this->app->bind('http.client', function ($app) {
            return $app->make(HttpClientService::class);
        });
    }
}
