<?php

namespace App\Http\Controllers;

use App\Exceptions\DemoPaymentException;
use App\Models\DemoOrder;
use App\Models\Product;
use App\Services\DemoPayment;
use App\Support\Base58;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DemoPaymentController extends Controller
{
    public function __construct(private DemoPayment $payments)
    {
        // Defence in depth if retired routes are accidentally reintroduced.
        abort_if(config('solana.presentation_only', true), 404);
    }

    private function owner(Request $request): string
    {
        if (! $request->session()->has('demo_owner')) { $request->session()->put('demo_owner', Str::random(64)); }
        return hash('sha256', $request->session()->get('demo_owner'));
    }

    private function authorizeOrder(Request $request, DemoOrder $demoOrder): void
    {
        abort_unless(hash_equals($demoOrder->session_hash, $this->owner($request)), 404);
    }

    private function result(DemoOrder $order)
    {
        return response()->json([
            'id' => $order->id, 'product_id' => $order->product_id, 'status' => $order->status,
            'payer' => $order->payer, 'recipient' => $order->recipient, 'lamports' => $order->lamports,
            'blockhash' => $order->blockhash, 'last_valid_block_height' => $order->last_valid_block_height,
            'memo' => $order->memo(), 'network' => 'devnet', 'signature' => $order->signature,
            'failure_reason' => $order->failure_reason,
            'explorer_url' => $order->signature ? 'https://explorer.solana.com/tx/'.$order->signature.'?cluster=devnet' : null,
        ])->header('Cache-Control', 'no-store');
    }

    private function handle(callable $operation)
    {
        try { return $this->result($operation()); }
        catch (DemoPaymentException $exception) {
            return response()->json(['error' => $exception->reason], $exception->httpStatus)->header('Cache-Control', 'no-store');
        }
    }

    public function store(Request $request, Product $product)
    {
        $data = $request->validate(['payer' => ['bail', 'required', 'string', 'max:44', fn ($attribute, $value, $fail) => Base58::address($value) ?: $fail(__('Invalid Solana public address.'))]]);
        return $this->handle(fn () => $this->payments->prepare($this->owner($request), $product->id, $data['payer']));
    }

    public function show(Request $request, DemoOrder $demoOrder)
    {
        $this->authorizeOrder($request, $demoOrder);
        return $this->result($demoOrder);
    }

    public function submit(Request $request, DemoOrder $demoOrder)
    {
        $this->authorizeOrder($request, $demoOrder);
        $data = $request->validate(['transaction' => ['nullable', 'string', 'max:1700']]);
        return $this->handle(function () use ($demoOrder, $data) {
            $wire = $data['transaction'] ?? $demoOrder->signed_transaction;
            if (! $wire) { throw new DemoPaymentException('invalidTransaction'); }
            return $this->payments->submit($demoOrder, $wire);
        });
    }

    public function verify(Request $request, DemoOrder $demoOrder)
    {
        $this->authorizeOrder($request, $demoOrder);
        return $this->handle(fn () => $this->payments->verify($demoOrder));
    }

    public function cancel(Request $request, DemoOrder $demoOrder)
    {
        $this->authorizeOrder($request, $demoOrder);
        return $this->handle(function () use ($demoOrder) {
            $updated = DemoOrder::whereKey($demoOrder->id)->where('status', 'pending')->whereNull('signature')->update(['status' => 'cancelled']);
            if (! $updated) { throw new DemoPaymentException('orderBusy', 409); }
            return $demoOrder->fresh();
        });
    }
}
