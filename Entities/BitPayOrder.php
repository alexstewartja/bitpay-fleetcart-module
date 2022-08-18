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

namespace Modules\BitpayFleetcart\Entities;

use BitPaySDK\Model\Invoice\Invoice;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Order\Entities\Order;
use Modules\Support\Eloquent\Model;

class BitPayOrder extends Model
{
    use SoftDeletes;

    protected $table = 'bitpay_orders';

    protected $fillable = ['invoice_id', 'invoice_token', 'invoice_url', 'invoice_status',
        'invoice_creation_time', 'invoice_expiration_time'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function updateStatus(Invoice $invoice): bool
    {
        if ($this->invoice_status !== $invoice->getStatus()) {
            return $this->update(['invoice_status' => $invoice->getStatus()]);
        }
        return false;
    }

    public static function isVirtualOrder(Order $order): bool
    {
        $virtualOrders = 0;

        foreach ($order->products as $orderProduct) {
            if ($orderProduct->product->virtual) {
                $virtualOrders ++;
            }
        }

        return $order->products()->count() === $virtualOrders;
    }
}
