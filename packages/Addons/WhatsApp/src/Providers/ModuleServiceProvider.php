<?php

namespace Addons\WhatsApp\Providers;

use Addons\WhatsApp\Models\WhatsAppConversation;
use Addons\WhatsApp\Models\WhatsAppMessage;
use Addons\WhatsApp\Models\WhatsAppSetting;
use Webkul\Core\Providers\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        WhatsAppSetting::class,
        WhatsAppConversation::class,
        WhatsAppMessage::class,
    ];
}
