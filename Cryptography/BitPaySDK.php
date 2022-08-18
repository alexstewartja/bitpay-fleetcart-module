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

namespace Modules\BitpayFleetcart\Cryptography;

use BitPayKeyUtils\KeyHelper\PrivateKey;
use BitPayKeyUtils\Storage\EncryptedFilesystemStorage;
use Illuminate\Support\Facades\Http;

class BitPaySDK
{
    /**
     * @param string $path
     * @param string $secret
     * @return bool
     * @throws \Throwable
     */
    public static function generatePK(string $path, string $secret): bool
    {
        $storageEngine = new EncryptedFilesystemStorage($secret);

        try {
            $privateKey = $storageEngine->load($path);

            throw_if(!$privateKey->isValid(), new \Exception());

            return true;
        } catch (\Throwable $exception) {
            try {
                $privateKey = new PrivateKey($path);
                $privateKey->generate();

                $storageEngine->persist($privateKey);

                return true;
            } catch (\Throwable $exception) {
                throw $exception;
            }
        }
    }

    /**
     * @param string $path
     * @param string $secret
     * @param bool $merchant
     * @param bool $payout
     * @param string $bitPayEnv
     * @return array
     * @throws \Exception
     */
    public static function generateToken(string $path, string $secret, bool $merchant = true, bool $payout = false, string $bitPayEnv = 'prod'): array
    {
        $returnData = [];
        $enabledFacades = [];

        if ($merchant) {
            $enabledFacades[] = 'merchant';
        }
        if ($payout) {
            $enabledFacades[] = 'payout';
        }

        $storageEngine = new EncryptedFilesystemStorage($secret);
        $privateKey = $storageEngine->load($path);

        $publicKey = $privateKey->getPublicKey();
        $sin = $publicKey->getSin()->__toString();

        $subDomain = ($bitPayEnv === 'test') ? 'test.' : '';
        $baseURI = "https://{$subDomain}bitpay.com";

        foreach ($enabledFacades as $facade) {
            // Token label is limited to 60 characters
            $tokenLabel = substr(ucwords(str_replace(" ", "-", config('app.name'))), 0, 36)
                . '__' . ucfirst($facade) . '__' . date('h-i-s_A');

            $postData = [
                'id' => $sin,
                'label' => $tokenLabel,
                'facade' => $facade,
            ];

            $response = Http::withHeaders([
                'x-accept-version' => '2.0.0',
                'content-type' => 'application/json',
            ])->post($baseURI . '/tokens', $postData);

            if ($response->successful()) {
                $response = json_decode($response->body());

                $token = $response->data[0]->token;
                $pairingCode = $response->data[0]->pairingCode;
                $pairingExpiration = date('M j, Y h:i:s A', intval($response->data[0]->pairingExpiration) / 1000);
                $approveLink = $baseURI . '/api-access-request?' .
                    http_build_query(['pairingCode' => $pairingCode]);

                $returnData[$facade] = [
                    'token' => $token,
                    'pairingCode' => $pairingCode,
                    'pairingExpiration' => $pairingExpiration,
                    'pairingExpirationTS' => $response->data[0]->pairingExpiration,
                    'approveLink' => $approveLink
                ];
            }
        }

        return $returnData;
    }
}
