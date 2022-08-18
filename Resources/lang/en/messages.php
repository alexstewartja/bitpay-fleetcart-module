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

return [
    'token_generated' => 'BitPay :facade token generated successfully. Approve it to start accepting BitPay payments.',
    'error_generating_token' => 'Error generating BitPay :facade API Token. Error message: :err',
    'cannot_access_pk' => 'Private Key is missing, inaccessible or corrupt.',
    'server_error' => 'Unable to retrieve API Token from BitPay server.',
    'action_error' => 'Unable to perform the requested action.',
    'ready_to_accept' => "Awesome! You're all set and ready to start accepting crypto payments!",
    'gateway_disabled' => 'The BitPay gateway is not enabled!',
    'payment_for_order' => 'Payment for Order #'
];
