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

namespace Modules\BitpayFleetcart\Admin;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Modules\Admin\Ui\Tab;
use Modules\Admin\Ui\Tabs;

class SettingTabsExtender
{
    public function extend(Tabs $tabs)
    {
        $tabs->group('payment_methods')
            ->add($this->bitpay());
    }


    private function bitpay()
    {
        $tokenDetails = array();

        $merchantTokenDetails = Session::get('bitpay_merchant_token_details_' .
            Str::substr(setting('bitpay_merchant_token'), 0, 8));

        if (!empty($merchantTokenDetails) && (date("Y-m-d\TH:i:sP") <
                date("Y-m-d\TH:i:sP", intval($merchantTokenDetails['pairingExpirationTS']) / 1000))) {
            $tokenDetails['merchant'] = Arr::only($merchantTokenDetails, ['pairingExpiration', 'approveLink']);
        }

        $hasMerchant = array_key_exists('merchant', $tokenDetails);

        return tap(new Tab('bitpay', trans('bitpay::settings.tabs.bitpay')), function (Tab $tab) use ($hasMerchant, $tokenDetails) {
            $tab->weight(61);

            $tab->fields([
                'bitpay_enabled',
                'translatable.bitpay_label',
                'translatable.bitpay_description',
                'bitpay_receive_webhooks',
                'bitpay_test_mode',
                'bitpay_merchant_token',
            ]);

            $tab->view('bitpay::admin.settings.tabs.bitpay', ['tokenDetails' => $tokenDetails, 'hasMerchant' => $hasMerchant]);
        });
    }

}
