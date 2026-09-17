<?php

namespace Addons\Zadarma\Providers;

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ZadarmaServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(Router $router)
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'zadarma');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'zadarma');

        Route::middleware(['web', 'admin_locale', 'user'])
            ->prefix(config('app.admin_path'))
            ->group(__DIR__.'/../Routes/admin-routes.php');

        $this->registerBreadcrumbs();

        $this->app->register(ModuleServiceProvider::class);
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(__DIR__.'/../Config/acl.php', 'acl');
    }

    /**
     * Register the breadcrumb trail for the settings screen. Registered
     * from here (rather than the app's routes/breadcrumbs.php) so the addon
     * stays self-contained and doesn't require touching core files.
     */
    protected function registerBreadcrumbs(): void
    {
        Breadcrumbs::for('settings.zadarma', function (BreadcrumbTrail $trail) {
            $trail->parent('settings');
            $trail->push(menu()->getLabel('settings.other_settings.zadarma', 'zadarma::app.menu.title'), route('admin.settings.zadarma.index'));
        });
    }
}
