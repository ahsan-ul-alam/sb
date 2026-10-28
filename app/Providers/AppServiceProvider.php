<?php

namespace App\Providers;

use App\Support\SiteContent;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
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
        // @v('css/app.css') => /css/app.css?v=<mtime>: relative (no mixed content) and cache-busted on every deploy
        Blade::directive('v', fn ($path) => "<?php echo '/'.ltrim({$path}, '/').'?v='.(@filemtime(public_path({$path})) ?: 1); ?>");

        View::composer(['auth', 'dashboard'], fn ($view) => $view->with('brand', SiteContent::brand()));
    }
}
