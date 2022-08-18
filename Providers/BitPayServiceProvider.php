<?php
/*
 * BitPay for FleetCart
 *
 * MIT License
 *
 * Copyright (c) 2022 Alex Stewart
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Modules\BitpayFleetcart\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Modules\Admin\Ui\Facades\TabManager;
use Modules\BitpayFleetcart\Admin\SettingTabsExtender;
use Modules\BitpayFleetcart\Constants\BitPayConst;
use Modules\BitpayFleetcart\Entities\BitPayOrder;
use Modules\BitpayFleetcart\Gateways\BitPay;
use Modules\Order\Entities\Order;
use Modules\Payment\Facades\Gateway;
use Modules\Setting\Entities\Setting;
use Modules\Transaction\Entities\Transaction;

class BitPayServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected $moduleName = 'BitPay';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'bitpay';

    protected $middleware = [];

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        if (!config('app.installed')) {
            return;
        }

        $this->registerMiddleware();
        $this->registerCascades();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        $this->setupPkStorage();
        $this->registerSettings();
        $this->registerBitPayGateway();

        TabManager::extend('settings', SettingTabsExtender::class);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);

        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath
        ], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Resources/lang'), $this->moduleNameLower);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }
        return $paths;
    }

    private function registerMiddleware()
    {
        foreach ($this->middleware as $name => $class) {
            $this->app['router']->aliasMiddleware($name, $class);
        }
    }

    private function registerCascades()
    {
        Order::softDeleted(function (Order $order) {
            $bitPayOrder = BitPayOrder::whereOrderId($order->id)->first();
            if ($bitPayOrder) {
                $bitPayOrder->delete();
            }
        });

        Transaction::creating(function (Transaction $transaction) {
            $tx = Transaction::whereOrderId($transaction->order_id);
            if ($tx->exists()) {
                $tx->forceDelete();
            }
        });
    }

    private function setupPkStorage()
    {
        $pkStoragePath = config('bitpay.private_key_path') ?: storage_path(BitPayConst::PK_PATH);
        if (!File::isDirectory($pkStoragePath)) {
            File::makeDirectory($pkStoragePath, 0755, true);
        }
    }

    private function registerSettings()
    {
        $pkSecret = config('bitpay.private_key_secret');
        if (empty(Setting::get('bitpay_pk_secret')) ||
            (!empty($pkSecret) && $pkSecret !== Setting::get('bitpay_pk_secret'))) {
            $pkSecret = $pkSecret ?: Str::random(32);
            Setting::set('bitpay_pk_secret', $pkSecret);
        }
    }

    private function enabled($paymentMethod)
    {
        if (app('inAdminPanel')) {
            return true;
        }

        return Setting::get("{$paymentMethod}_enabled");
    }

    private function registerBitPayGateway()
    {
        if ($this->enabled($this->moduleNameLower)) {
            Gateway::register($this->moduleNameLower, new BitPay);
        }
    }
}
