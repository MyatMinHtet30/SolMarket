<?php

namespace App\Services;

use App\Exceptions\DemoPaymentException;
use App\Models\DemoOrder;
use App\Support\Base58;

/** Deliberately limited legacy-wire parser: exactly one native transfer and signed memo. */
class DemoTransaction
{
    public const SYSTEM = '11111111111111111111111111111111';
    public const MEMO = 'MemoSq4gqABAXKb96qnH8TysNcWxMyWCqXgDLGmfcHr';

    public function inspect(string $encoded, DemoOrder $order): array
    {
        $wire = base64_decode($encoded, true);
        if ($wire === false || strlen($wire) > 1232) { $this->invalid(); }
        $offset = 0;
        $read = function (int $length) use ($wire, &$offset): string {
            if ($length < 0 || $offset + $length > strlen($wire)) { $this->invalid(); }
            $value = substr($wire, $offset, $length);
            $offset += $length;
            return $value;
        };
        // All lengths in this constrained format fit into one compact-u16 byte.
        $length = function () use ($read): int {
            $value = ord($read(1));
            if ($value > 127) { $this->invalid(); }
            return $value;
        };
        if ($length() !== 1) { $this->invalid(); }
        $signatureBytes = $read(64);
        if ($signatureBytes === str_repeat("\0", 64) || $read(3) !== "\x01\x00\x02" || $length() !== 4) { $this->invalid(); }
        $keys = [];
        for ($i = 0; $i < 4; $i++) { $keys[] = Base58::encode($read(32)); }
        if ($keys[0] !== $order->payer || $keys[1] !== $order->recipient
            || count(array_unique($keys)) !== 4
            || ! in_array(self::SYSTEM, array_slice($keys, 2), true)
            || ! in_array(self::MEMO, array_slice($keys, 2), true)
            || Base58::encode($read(32)) !== $order->blockhash || $length() !== 2) { $this->invalid(); }
        foreach ([self::SYSTEM, self::MEMO] as $program) {
            $programIndex = ord($read(1));
            $accounts = $read($length());
            $data = $read($length());
            $expectedAccounts = $program === self::SYSTEM ? "\x00\x01" : "\x00";
            $expectedData = $program === self::SYSTEM ? pack('V3', 2, $order->lamports, 0) : $order->memo();
            if (($keys[$programIndex] ?? null) !== $program || $accounts !== $expectedAccounts || ! hash_equals($expectedData, $data)) { $this->invalid(); }
        }
        if ($offset !== strlen($wire)) { $this->invalid(); }

        return ['signature' => Base58::encode($signatureBytes), 'hash' => hash('sha256', $wire)];
    }

    private function invalid(): never { throw new DemoPaymentException('invalidTransaction'); }
}
