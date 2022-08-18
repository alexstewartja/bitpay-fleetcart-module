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

namespace Modules\BitpayFleetcart\Gateways;

use BitPaySDK\Client as BitpayClient;
use BitPaySDK\Env;
use BitPaySDK\Exceptions\BitPayException;
use BitPaySDK\Model\Invoice\Buyer;
use BitPaySDK\Model\Invoice\Invoice;
use BitPaySDK\Tokens;
use Exception;
use Illuminate\Http\Request;
use Modules\BitpayFleetcart\Constants\BitPayConst;
use Modules\BitpayFleetcart\Entities\BitPayOrder;
use Modules\BitpayFleetcart\Responses\BitPayResponse;
use Modules\Order\Entities\Order;
use Modules\Payment\GatewayInterface;
use Modules\Setting\Entities\Setting;

class BitPay implements GatewayInterface
{
    public const gatewayCode = 'bitpay';
    public $label;
    public $description;

    public function __construct()
    {
        $this->label = setting('bitpay_label');
        $this->description = setting('bitpay_description');
    }

    /**
     * @throws \BitPaySDK\Exceptions\BitPayException
     */
    public static function client(): BitpayClient
    {
        return BitpayClient::create()->withData(
            setting('bitpay_test_mode') ? Env::Test : Env::Prod,
            (config('bitpay.private_key_path') ?: storage_path(BitPayConst::PK_PATH)) . DIRECTORY_SEPARATOR . 'bitpay.pem',
            new Tokens(setting('bitpay_merchant_token')),
            config('bitpay.private_key_secret') ?: setting('bitpay_pk_secret')
        );
    }

    public function purchase(Order $order, Request $request)
    {
        $invoiceData = new Invoice(
            $order->total->convertToCurrentCurrency()->round()->amount(),
            currency()
        );

        $invoiceData->setOrderId($order->id);

        $invoiceData->setExtendedNotifications(true);
        $ipnUrl = route('bitpay.handle-webhook');
        if (config('app.env') === 'local' && !empty(config('bitpay.dev_url', ''))) {
            $ipnUrl = config('bitpay.dev_url') . '/api/bitpay/webhook';
        }
        $invoiceData->setNotificationURL($ipnUrl);

        $invoiceData->setAutoRedirect(true);
        $invoiceData->setRedirectURL(route('checkout.complete.store', ['orderId' => $order->id,
            'paymentMethod' => self::gatewayCode]));
        $invoiceData->setCloseURL(route('checkout.payment_canceled.store', ['orderId' => $order->id,
            'paymentMethod' => self::gatewayCode]));
        $invoiceData->setItemDesc(setting('store_name') . ' - ' . trans('bitpay::messages.payment_for_order') . $order->id);

        $buyer = new Buyer();
        $buyer->setName($order->customer_full_name);
        $buyer->setEmail($order->customer_email);
        $buyer->setAddress1($order->billing_address_1);
        $buyer->setAddress2($order->billing_address_2 ?? '');
        $buyer->setLocality($order->billing_city);
        $buyer->setRegion($order->billing_state);
        $buyer->setPostalCode($order->billing_zip);
        $buyer->setCountry($order->billing_country);
        $buyer->setNotify(true);

        $invoiceData->setBuyer($buyer);

        try {
            $invoice = self::client()->createInvoice($invoiceData);

            $bitPayOrder = new BitPayOrder();
            $bitPayOrder->fill([
                'invoice_id' => $invoice->getId(),
                'invoice_token' => $invoice->getToken(),
                'invoice_url' => $invoice->getUrl(),
                'invoice_status' => $invoice->getStatus(),
                'invoice_creation_time' => $invoice->getInvoiceTime(),
                'invoice_expiration_time' => $invoice->getExpirationTime()
            ]);
            $bitPayOrder->order()->associate($order);
            $bitPayOrder->save();
        } catch (BitPayException $exception) {
            throw new Exception($exception->getMessage());
        }

        return new BitPayResponse($order, $invoice);
    }

    public function complete(Order $order)
    {
        try {
            $bitPayOrder = BitPayOrder::whereOrderId($order->id)->first();
            $invoice = self::client()->getInvoice($bitPayOrder->invoice_id);
        } catch (BitPayException $exception) {
            throw new Exception($exception->getMessage());
        }

        return new BitPayResponse($order, $invoice);
    }
}
