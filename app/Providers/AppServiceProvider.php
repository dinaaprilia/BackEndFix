<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Aktivitas;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Artisan;


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
    public function boot()
{
    if (env('APP_ENV') === 'production') {
        URL::forceScheme('https');
    }
        if (!file_exists(public_path('storage'))) {
        Artisan::call('storage:link');
    }
}
}
