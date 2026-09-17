<?php

namespace Addons\Zadarma\Providers;

use Addons\Zadarma\Repositories\ZadarmaExtensionMappingRepository;
use Addons\Zadarma\Repositories\ZadarmaSettingRepository;
use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Core\ViewRenderEventManager;

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

        $this->registerPhoneButton();

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

    /**
     * Inject the floating softphone button on every admin page via Krayin's
     * `view_render_event` extension point — this avoids editing the core
     * Admin package's header/layout files directly, which would conflict
     * with future upstream Krayin updates.
     *
     * Only shown when: the addon is enabled and configured, the current
     * user has a SIP extension mapped to them, and their role has the
     * `zadarma_phone` permission.
     */
    protected function registerPhoneButton(): void
    {
        Event::listen('admin.layout.body.after', function (ViewRenderEventManager $viewRenderEventManager) {
            if (! app()->bound('auth') || ! auth()->guard('user')->check()) {
                return;
            }

            $user = auth()->guard('user')->user();

            if (! $this->userHasPhonePermission($user)) {
                return;
            }

            if (! app(ZadarmaSettingRepository::class)->isEnabled()) {
                return;
            }

            if (! app(ZadarmaExtensionMappingRepository::class)->findExtensionByUserId($user->id)) {
                return;
            }

            $viewRenderEventManager->addTemplate('zadarma::partials.phone-button');
        });
    }

    /**
     * `Webkul\User\Models\User::hasPermission()` does `in_array($permission,
     * $role->permissions)` without checking for `permission_type === 'all'`
     * first, so it throws a TypeError for full-admin roles (whose
     * `permissions` column is null). That's a pre-existing core bug, dormant
     * because nothing in core calls it directly for an 'all' role — worked
     * around here rather than patched in the core package.
     */
    protected function userHasPhonePermission($user): bool
    {
        if (! $role = $user->role) {
            return false;
        }

        if ($role->permission_type === 'all') {
            return true;
        }

        return in_array('zadarma_phone', $role->permissions ?? []);
    }
}
