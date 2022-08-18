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

namespace Modules\BitpayFleetcart\Listeners;

use BitPaySDK\Exceptions\BitPayException;
use BitPaySDK\Model\Invoice\Invoice;
use BitPaySDK\Model\Invoice\InvoiceStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\BitpayFleetcart\Constants\BitPayConst;
use Modules\BitpayFleetcart\Entities\BitPayOrder;
use Modules\BitpayFleetcart\Events\BitPayWebhookEvent;
use Modules\BitpayFleetcart\Gateways\BitPay;
use Modules\BitpayFleetcart\Responses\BitPayResponse;
use Modules\Order\Entities\Order;
use Modules\Order\Events\OrderStatusChanged;

class BitPayWebhookListener
{
    /**
     * Handle the event.
     *
     * @param BitPayWebhookEvent $event
     * @return void
     */
    public function handle(BitPayWebhookEvent $event)
    {
        $payload = $event->payload;
        if (in_array($payload['event']['code'], array_keys(BitPayConst::INVOICE_WEBHOOK_CODES))) {
            try {
                $invoice = BitPay::client()->getInvoice($payload['data']['id']);
                $order = Order::whereId($invoice->getOrderId())->first();

                if ($order) {
                    $bitPayOrder = BitPayOrder::whereOrderId($order->id)->first();

                    // Validate invoice token, if possible
                    if ($bitPayOrder && ($invoice->getToken() !== $bitPayOrder->invoice_token)) {
                        return;
                    } else {
                        $bitPayOrder->updateStatus($invoice);
                    }

                    $invoice_status = $invoice->getStatus();

                    // In case a transaction wasn't previously recorded for this order
                    if ($invoice_status !== InvoiceStatus::Paid) {
                        $this->maybeStoreOrderTx($invoice, $order);
                    }

                    $handler = 'handle' . Str::ucfirst($invoice_status) . 'Invoice';

                    if (method_exists($this, $handler)) {
                        // Handle specific Invoice statuses
                        $this->{$handler}($invoice, $order);
                    }
                }
            } catch (BitPayException $e) {
                Log::error($e);
            }
        }
    }

    private function maybeStoreOrderTx(Invoice $invoice, Order $order)
    {
        if (!$order->transaction()->exists()) {
            $order->storeTransaction(new BitPayResponse($order, $invoice));
        }
    }

    private function updateOrderStatusAndNotify(Order $order, array $statusesToQuery, string $statusToApply, bool $isIn = true)
    {
        if ($isIn) {
            $equality = in_array($order->status, $statusesToQuery);
        } else {
            $equality = !in_array($order->status, $statusesToQuery);
        }

        if ($equality) {
            $orderUpdated = $order->update(['status' => $statusToApply]);

            if ($orderUpdated && $order->transaction()->exists()) {
                event(new OrderStatusChanged($order->refresh()));
            }
        }
    }

    private function completeIfVirtualOrder(Order $order, array $statusesToQuery)
    {
        $statusToApply = BitPayOrder::isVirtualOrder($order) ? Order::COMPLETED : Order::PROCESSING;

        $this->updateOrderStatusAndNotify(
            $order,
            $statusesToQuery,
            $statusToApply
        );
    }

    /**
     * @throws \Throwable
     */
    private function handlePaidInvoice(Invoice $invoice, Order $order)
    {
        $invoiceExceptionStatus = $invoice->getExceptionStatus();
        $statusToApply = ($invoiceExceptionStatus &&
            $invoiceExceptionStatus === BitPayConst::INVOICE_EXCEPTION_PAIDPARTIAL) ?
            Order::CANCELED : Order::PENDING;

        $this->updateOrderStatusAndNotify(
            $order,
            [Order::PENDING_PAYMENT],
            $statusToApply
        );
    }

    private function handleConfirmedInvoice(Invoice $invoice, Order $order)
    {
        $this->completeIfVirtualOrder($order, [Order::PENDING, Order::PENDING_PAYMENT]);
    }

    private function handleCompleteInvoice(Invoice $invoice, Order $order)
    {
        $this->completeIfVirtualOrder($order, [Order::PENDING, Order::PENDING_PAYMENT, Order::ON_HOLD]);
    }

    private function handleExpiredInvoice(Invoice $invoice, Order $order)
    {
        $this->updateOrderStatusAndNotify(
            $order,
            [Order::CANCELED],
            Order::CANCELED,
            false
        );
    }

    private function handleInvalidInvoice(Invoice $invoice, Order $order)
    {
        $this->updateOrderStatusAndNotify(
            $order,
            [Order::ON_HOLD],
            Order::ON_HOLD,
            false
        );
    }
}
