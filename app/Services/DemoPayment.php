<?php

namespace App\Services;

use App\Exceptions\DemoPaymentException;
use App\Models\DemoOrder;
use App\Support\Base58;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class DemoPayment
{
    public function __construct(private DevnetRpc $rpc, private DemoTransaction $transactions) {}

    public static function configured(): bool
    {
        if (config('solana.presentation_only', true)) { return false; }
        $amount = config('solana.demo_lamports');
        return config('solana.payments_enabled') === true && config('solana.network') === 'devnet'
            && Base58::address(config('solana.demo_recipient'))
            && ! in_array(config('solana.demo_recipient'), [DemoTransaction::MEMO], true)
            && filter_var($amount, FILTER_VALIDATE_INT) !== false && $amount >= 1 && $amount <= 10000000;
    }

    public function prepare(string $owner, int $productId, string $payer): DemoOrder
    {
        if (! self::configured()) { throw new DemoPaymentException('notConfigured', 503); }
        $existing = DemoOrder::where('session_hash', $owner)->where('product_id', $productId)->where('status', 'pending')->first();
        if ($existing && ($existing->signature || ($existing->payer === $payer && $existing->expires_at->isFuture()))) { return $existing; }
        if ($existing) {
            $cancelled = DemoOrder::whereKey($existing->id)->where('status', 'pending')->whereNull('signature')->update(['status' => 'cancelled']);
            if (! $cancelled) { return $existing->fresh(); }
        }
        if ($payer === config('solana.demo_recipient') || $payer === DemoTransaction::MEMO) { throw new DemoPaymentException('sameRecipient'); }
        $latest = $this->rpc->call('getLatestBlockhash', [['commitment' => 'confirmed']]);
        $hash = data_get($latest, 'value.blockhash');
        $height = data_get($latest, 'value.lastValidBlockHeight');
        if (! is_string($hash) || strlen(Base58::decode($hash) ?? '') !== 32 || ! is_int($height)) { throw new DemoPaymentException('rpcUnavailable', 503); }
        try {
            return DemoOrder::create([
                'session_hash' => $owner, 'product_id' => $productId, 'payer' => $payer,
                'recipient' => config('solana.demo_recipient'), 'lamports' => (int) config('solana.demo_lamports'),
                'blockhash' => $hash, 'last_valid_block_height' => $height, 'expires_at' => now()->addMinutes(10),
            ]);
        } catch (UniqueConstraintViolationException) { throw new DemoPaymentException('orderBusy', 409); }
    }

    public function submit(DemoOrder $order, string $wire): DemoOrder
    {
        if (config('solana.presentation_only', true)) { throw new DemoPaymentException('paymentsDisabled', 404); }
        if ($order->status === 'confirmed') { return $order; }
        if ($order->status !== 'pending') { throw new DemoPaymentException('orderClosed'); }
        if (! $order->signature && $order->expires_at->isPast()) { throw new DemoPaymentException('expired'); }
        $transaction = $this->transactions->inspect($wire, $order);
        try {
            DB::transaction(function () use ($order, $wire, $transaction) {
                $current = DemoOrder::findOrFail($order->id);
                if ($current->status !== 'pending' || ($current->signature && $current->signature !== $transaction['signature'])) { throw new DemoPaymentException('orderClosed'); }
                if (! $current->signature) {
                    $current->update(['signature' => $transaction['signature'], 'signed_transaction' => $wire]);
                }
            });
        } catch (UniqueConstraintViolationException) { throw new DemoPaymentException('duplicateSignature', 409); }
        // Reserve the signature before sending: a timeout must not enable a second payment.
        $signature = $this->rpc->call('sendTransaction', [$wire, ['encoding' => 'base64', 'skipPreflight' => false, 'preflightCommitment' => 'confirmed', 'maxRetries' => 2]]);
        if ($signature !== $transaction['signature']) { throw new DemoPaymentException('rpcUnavailable', 503); }
        return $order->fresh();
    }

    public function verify(DemoOrder $order): DemoOrder
    {
        if (config('solana.presentation_only', true)) { throw new DemoPaymentException('paymentsDisabled', 404); }
        if ($order->status !== 'pending' || ! $order->signature) { return $order; }
        $result = $this->rpc->call('getTransaction', [$order->signature, ['commitment' => 'finalized', 'encoding' => 'base64', 'maxSupportedTransactionVersion' => 0]]);
        if ($result === null) {
            $height = $this->rpc->call('getBlockHeight', [['commitment' => 'finalized']]);
            if (is_int($height) && $height > $order->last_valid_block_height) {
                $statuses = $this->rpc->call('getSignatureStatuses', [[$order->signature], ['searchTransactionHistory' => true]]);
                if (is_array($statuses) && array_key_exists(0, $statuses['value'] ?? []) && $statuses['value'][0] === null) {
                    $order->update(['status' => 'failed', 'failure_reason' => 'expired']);
                }
            }
            return $order;
        }
        if (! is_array($result) || ! is_array($result['meta'] ?? null) || ! array_key_exists('err', $result['meta'])) { throw new DemoPaymentException('invalidTransaction'); }
        $encoded = $result['transaction'][0] ?? null;
        if (! is_string($encoded) || ($result['transaction'][1] ?? null) !== 'base64') { throw new DemoPaymentException('invalidTransaction'); }
        $actual = $this->transactions->inspect($encoded, $order);
        $expected = $this->transactions->inspect($order->signed_transaction, $order);
        $time = $result['blockTime'] ?? null;
        $meta = $result['meta'];
        if ($actual['signature'] !== $order->signature || ! hash_equals($actual['hash'], $expected['hash'])
            || ! is_int($result['slot'] ?? null) || $result['slot'] < 1
            || ! is_int($time) || $time < $order->created_at->timestamp - 30 || $time > $order->expires_at->timestamp) {
            throw new DemoPaymentException('invalidTransaction');
        }
        if ($meta['err'] !== null) {
            $order->update(['status' => 'failed', 'failure_reason' => 'chainFailed']);
            return $order;
        }
        if (! is_int($meta['fee'] ?? null) || $meta['fee'] < 0
            || ! is_int($meta['preBalances'][0] ?? null) || ! is_int($meta['postBalances'][0] ?? null)
            || ! is_int($meta['preBalances'][1] ?? null) || ! is_int($meta['postBalances'][1] ?? null)
            || $meta['preBalances'][0] - $meta['postBalances'][0] !== $order->lamports + $meta['fee']
            || $meta['postBalances'][1] - $meta['preBalances'][1] !== $order->lamports
            || ! empty($meta['innerInstructions'])) { throw new DemoPaymentException('invalidTransaction'); }
        DemoOrder::whereKey($order->id)->where('status', 'pending')->where('signature', $order->signature)
            ->update(['status' => 'confirmed', 'verified_at' => now()]);
        return $order->fresh();
    }
}
