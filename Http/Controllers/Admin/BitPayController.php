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

namespace Modules\BitpayFleetcart\Http\Controllers\Admin;

use BitPaySDK\Model\Facade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Modules\BitpayFleetcart\Constants\BitPayConst;
use Modules\BitpayFleetcart\Cryptography\BitPaySDK;
use Modules\BitpayFleetcart\Events\BitPayWebhookEvent;
use Modules\Setting\Entities\Setting;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class BitPayController extends Controller
{
    public function generateApiToken(Request $request)
    {
        if (Setting::get('bitpay_enabled', false)) {
            $bEnv = setting('bitpay_test_mode') ? 'test' : 'prod';
            $pkPath = (config('bitpay.private_key_path') ?: storage_path(BitPayConst::PK_PATH)) . DIRECTORY_SEPARATOR . 'bitpay.pem';
            $pkSecret = config('bitpay.private_key_secret') ?: setting('bitpay_pk_secret');
            $facade = Facade::Merchant;

            try {
                if (BitPaySDK::generatePK($pkPath, $pkSecret)) {
                    $tkOutput = BitPaySDK::generateToken($pkPath, $pkSecret, true, false, $bEnv);

                    if (key_exists($facade, $tkOutput)) {

                        $sessionKeyPrefix = "bitpay_{$facade}_token_details_";
                        $sessionKey = $sessionKeyPrefix . Str::substr(Setting::get("bitpay_{$facade}_token"), 0, 8);
                        if (Session::has($sessionKey)) {
                            Session::remove($sessionKey);
                        }

                        Setting::set("bitpay_{$facade}_token", $tkOutput[$facade]['token']);
                        Session::put(
                            $sessionKeyPrefix . Str::substr($tkOutput[$facade]['token'], 0, 8),
                            $tkOutput[$facade]
                        );

                        return Response::json(array_merge(Arr::only($tkOutput[$facade], ['token', 'pairingExpiration', 'approveLink']),
                            ['message' => trans('bitpay::messages.token_generated', ['facade' => $facade])]));
                    }
                }

                throw new \Exception(trans('bitpay::messages.server_error'));
            } catch (\Throwable $exception) {
                return Response::json(['message' => trans('bitpay::messages.error_generating_token', [
                    'facade' => $facade,
                    'err' => $exception->getMessage()
                ])], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return Response::json(['message' => trans('bitpay::messages.gateway_disabled')], ResponseAlias::HTTP_FORBIDDEN);
    }

    public function confirmApprovedApiToken(): JsonResponse
    {
        if (Setting::get('bitpay_enabled', false)) {
            $facade = Facade::Merchant;
            $status = ResponseAlias::HTTP_BAD_REQUEST;
            $message = trans('bitpay::messages.action_error');

            $sessionKeyPrefix = "bitpay_{$facade}_token_details_";
            $sessionKey = $sessionKeyPrefix . Str::substr(Setting::get("bitpay_{$facade}_token"), 0, 8);
            if (Session::has($sessionKey)) {
                Session::remove($sessionKey);

                $status = ResponseAlias::HTTP_OK;
                $message = trans('bitpay::messages.ready_to_accept');
            }

            return Response::json(['message' => $message], $status);
        }

        return Response::json(['message' => trans('bitpay::messages.gateway_disabled')], ResponseAlias::HTTP_FORBIDDEN);
    }

    public function handleWebhook(Request $request)
    {
        $payload = json_decode($request->getContent(), true);

        BitPayWebhookEvent::dispatch($payload);

        return response('', ResponseAlias::HTTP_OK);
    }
}
