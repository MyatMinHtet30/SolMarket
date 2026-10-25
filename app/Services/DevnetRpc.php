<?php

namespace App\Services;

use App\Exceptions\DemoPaymentException;
use Illuminate\Support\Facades\Http;

class DevnetRpc
{
    public const URL = 'https://api.devnet.solana.com';
    public const GENESIS = 'EtWTRABZaYq6iMfeYKouRu166VU2xqa1wcaWoxPkrZBG';
    private bool $checked = false;

    public function call(string $method, array $params = []): mixed
    {
        if (config('solana.presentation_only', true)) { throw new DemoPaymentException('paymentsDisabled', 404); }
        if (! $this->checked) {
            if ($this->request('getGenesisHash', []) !== self::GENESIS) {
                throw new DemoPaymentException('wrongNetwork', 503);
            }
            $this->checked = true;
        }
        return $this->request($method, $params);
    }

    private function request(string $method, array $params): mixed
    {
        try {
            $response = Http::connectTimeout(5)->timeout(15)->withoutRedirecting()->post(self::URL, [
                'jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params,
            ]);
            $data = $response->json();
            if ($method === 'sendTransaction' && in_array(data_get($data, 'error.code'), [-32002, -32003, -32602], true)) {
                throw new DemoPaymentException('submissionRejected');
            }
            if (! $response->successful() || ! is_array($data) || isset($data['error']) || ! array_key_exists('result', $data)) {
                throw new \RuntimeException('RPC unavailable');
            }
            return $data['result'];
        } catch (DemoPaymentException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new DemoPaymentException('rpcUnavailable', 503);
        }
    }
}
