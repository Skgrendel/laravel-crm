<?php

namespace Addons\Zadarma\Providers;

use Addons\Zadarma\Models\ZadarmaSetting;
use Webkul\Core\Providers\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        ZadarmaSetting::class,
    ];
}
