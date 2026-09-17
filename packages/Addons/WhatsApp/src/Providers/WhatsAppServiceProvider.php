<?php

namespace Addons\WhatsApp\Providers;

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Core\ViewRenderEventManager;

class WhatsAppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(Router $router)
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'whatsapp');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'whatsapp');

        Route::middleware(['web', 'admin_locale', 'user'])
            ->prefix(config('app.admin_path'))
            ->group(__DIR__.'/../Routes/admin-routes.php');

        $this->registerBreadcrumbs();

        $this->registerChatPanel();

        $this->app->register(ModuleServiceProvider::class);
    }

    /**
     * Inject the chat panel (Fase 2.3) into every Lead's view via Krayin's
     * `view_render_event` extension point, without touching core's
     * `leads/view.blade.php`. Shown for any Lead the user can already see —
     * an empty conversation just renders an empty panel; there's no
     * separate "this Lead uses WhatsApp" flag to gate on.
     */
    protected function registerChatPanel(): void
    {
        Event::listen('admin.leads.view.right.before', function (ViewRenderEventManager $viewRenderEventManager) {
            if (! bouncer()->hasPermission('whatsapp_chat')) {
                return;
            }

            $lead = $viewRenderEventManager->getParam('lead');

            if (! $lead) {
                return;
            }

            $viewRenderEventManager->addTemplate(view('whatsapp::partials.chat-panel', ['lead' => $lead])->render());
        });
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
        Breadcrumbs::for('settings.whatsapp', function (BreadcrumbTrail $trail) {
            $trail->parent('settings');
            $trail->push(menu()->getLabel('settings.other_settings.whatsapp', 'whatsapp::app.menu.title'), route('admin.settings.whatsapp.index'));
        });
    }
}
