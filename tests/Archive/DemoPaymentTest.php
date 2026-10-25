<?php

namespace Tests\Feature;

use App\Models\DemoOrder;
use App\Models\Product;
use App\Services\DemoTransaction;
use App\Services\DevnetRpc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoPaymentTest extends TestCase
{
    use RefreshDatabase;

    private array $fixture;
    private Product $product;
    private string $owner = 'test-session-owner';
    private array $rpcCalls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = json_decode(file_get_contents(base_path('tests/support/demo-transaction.json')), true);
        $this->seed();
        $this->product = Product::first();
        config(['solana.payments_enabled' => true, 'solana.demo_recipient' => $this->fixture['recipient'], 'solana.demo_lamports' => 1000000]);
        $this->withSession(['demo_owner' => $this->owner]);
        Http::preventStrayRequests();
    }

    private function order(array $overrides = []): DemoOrder
    {
        return DemoOrder::create(array_merge([
            'id' => $this->fixture['id'], 'session_hash' => hash('sha256', $this->owner),
            'product_id' => $this->product->id, 'payer' => $this->fixture['payer'], 'recipient' => $this->fixture['recipient'],
            'lamports' => 1000000, 'blockhash' => $this->fixture['blockhash'],
            'last_valid_block_height' => 500, 'expires_at' => now()->addMinutes(10),
        ], $overrides));
    }

    private function chainResult(array $overrides = []): array
    {
        return array_replace_recursive([
            'slot' => 400, 'blockTime' => now()->timestamp, 'transaction' => [$this->fixture['wire'], 'base64'],
            'meta' => ['err' => null, 'fee' => 5000, 'preBalances' => [10000000, 1000000, 1, 1],
                'postBalances' => [8995000, 2000000, 1, 1], 'innerInstructions' => []],
        ], $overrides);
    }

    private function rpc(?array $transaction = null, array $overrides = []): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($transaction, $overrides) {
            $this->assertSame(DevnetRpc::URL, $request->url());
            $method = $request['method'];
            $this->rpcCalls[] = $method;
            $results = [
                'getGenesisHash' => DevnetRpc::GENESIS,
                'getLatestBlockhash' => ['value' => ['blockhash' => $this->fixture['blockhash'], 'lastValidBlockHeight' => 500]],
                'sendTransaction' => app(DemoTransaction::class)->inspect($this->fixture['wire'], $this->orderForWire())['signature'],
                'getTransaction' => $transaction, 'getBlockHeight' => 450,
                'getSignatureStatuses' => ['value' => [null]],
            ];
            // PHP eagerly evaluates arrays; computing the signature needs no order from the DB.
            return Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => array_key_exists($method, $overrides) ? $overrides[$method] : $results[$method]]);
        });
    }

    private function orderForWire(): DemoOrder
    {
        return new DemoOrder(['id' => $this->fixture['id'], 'payer' => $this->fixture['payer'], 'recipient' => $this->fixture['recipient'], 'lamports' => 1000000, 'blockhash' => $this->fixture['blockhash']]);
    }

    private function submit(DemoOrder $order)
    {
        return $this->postJson('/demo-orders/'.$order->id.'/submit', ['transaction' => $this->fixture['wire']]);
    }

    public function test_configuration_is_required_and_amount_never_comes_from_eur_or_client(): void
    {
        config(['solana.demo_recipient' => null]);
        $this->postJson(route('demo-orders.store', $this->product), ['payer' => $this->fixture['payer']])->assertStatus(503)->assertJsonPath('error', 'notConfigured');
        $this->assertDatabaseCount('demo_orders', 0);
        config(['solana.demo_recipient' => $this->fixture['recipient']]);
        $this->rpc();
        $this->postJson(route('demo-orders.store', $this->product), ['payer' => $this->fixture['payer'], 'lamports' => 999999999, 'recipient' => $this->fixture['payer']])
            ->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('lamports', 1000000)->assertJsonPath('recipient', $this->fixture['recipient'])->assertJsonPath('signature', null);
        $this->assertDatabaseCount('demo_orders', 1);
        $this->assertSame('24.00', $this->product->fresh()->price);
    }

    public function test_wrong_cluster_and_unsafe_demo_configurations_fail_closed(): void
    {
        $this->rpc(overrides: ['getGenesisHash' => '5eykt4UsFv8P8NJdTREpY1vzqKqZKvdpKuc147dw2N9d']);
        $this->postJson(route('demo-orders.store', $this->product), ['payer' => $this->fixture['payer']])->assertStatus(503)->assertJsonPath('error', 'wrongNetwork');
        foreach (['solana.network' => 'mainnet', 'solana.demo_lamports' => 10000001, 'solana.demo_recipient' => 'not-an-address'] as $key => $value) {
            $before = config($key);
            config([$key => $value]);
            $this->postJson(route('demo-orders.store', $this->product), ['payer' => $this->fixture['payer']])->assertStatus(503);
            config([$key => $before]);
        }
        $this->assertDatabaseCount('demo_orders', 0);
    }

    public function test_signed_transfer_stays_pending_until_backend_observes_finalized_success(): void
    {
        $order = $this->order();
        $this->rpc();
        $this->submit($order)->assertOk()->assertJsonPath('status', 'pending');
        $this->assertNotNull($order->fresh()->signature);
        $this->assertNull($order->fresh()->verified_at);
        $this->postJson('/demo-orders/'.$order->id.'/verify')->assertOk()->assertJsonPath('status', 'pending');
        $this->rpc($this->chainResult());
        $response = $this->postJson('/demo-orders/'.$order->id.'/verify')->assertOk()->assertJsonPath('status', 'confirmed');
        $this->assertStringEndsWith('?cluster=devnet', $response->json('explorer_url'));
        $this->assertNotNull($order->fresh()->verified_at);
        Http::assertSent(fn ($request) => $request['method'] === 'getTransaction' && $request['params'][1]['commitment'] === 'finalized');
        $this->postJson('/demo-orders/'.$order->id.'/verify')->assertOk()->assertJsonPath('status', 'confirmed');
    }

    public function test_payer_recipient_amount_blockhash_memo_and_additional_instructions_are_checked_before_broadcast(): void
    {
        $order = $this->order();
        $this->rpc();
        foreach (['payer' => $this->fixture['recipient'], 'recipient' => $this->fixture['payer'], 'lamports' => 1000001, 'blockhash' => $this->fixture['payer']] as $field => $value) {
            $original = $order->$field;
            $order->update([$field => $value]);
            $this->submit($order)->assertStatus(422)->assertJsonPath('error', 'invalidTransaction');
            $order->update([$field => $original]);
        }
        foreach ([base64_encode(base64_decode($this->fixture['wire'])."\0"), base64_encode(str_replace('SolMarket demo ', 'WrongMemo demo ', base64_decode($this->fixture['wire']))), 'invalid%%'] as $wire) {
            $this->postJson('/demo-orders/'.$order->id.'/submit', ['transaction' => $wire])->assertStatus(422);
        }
        $this->assertNull($order->fresh()->signature);
        Http::assertNothingSent();
    }

    public function test_chain_failures_and_changed_finalized_balances_never_show_success(): void
    {
        $order = $this->order();
        $this->rpc();
        $this->submit($order)->assertOk();
        $this->rpc($this->chainResult(['meta' => ['postBalances' => [8995000, 1999999, 1, 1]]]));
        $this->postJson('/demo-orders/'.$order->id.'/verify')->assertStatus(422)->assertJsonPath('error', 'invalidTransaction');
        $this->assertSame('pending', $order->fresh()->status);
        $this->rpc($this->chainResult(['meta' => ['err' => ['InstructionError' => [0, 'InsufficientFunds']]]]));
        $this->postJson('/demo-orders/'.$order->id.'/verify')->assertOk()->assertJsonPath('status', 'failed')->assertJsonPath('failure_reason', 'chainFailed');
        $this->assertNull($order->fresh()->verified_at);
    }

    public function test_order_is_session_scoped_and_cancellation_cannot_erase_submitted_orders(): void
    {
        $order = $this->order();
        $this->withSession(['demo_owner' => 'another-browser']);
        foreach (['', '/submit', '/verify', '/cancel'] as $action) {
            ($action === '' ? $this->getJson('/demo-orders/'.$order->id) : $this->postJson('/demo-orders/'.$order->id.$action))->assertNotFound();
        }
        $this->withSession(['demo_owner' => $this->owner]);
        $this->rpc();
        $this->submit($order)->assertOk();
        $this->postJson('/demo-orders/'.$order->id.'/cancel')->assertStatus(409);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_rejected_unsigned_orders_can_be_cancelled_without_any_rpc_calls(): void
    {
        $order = $this->order();
        $this->postJson('/demo-orders/'.$order->id.'/cancel')->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertNull($order->fresh()->signature);
        Http::assertNothingSent();
    }

    public function test_duplicate_signature_is_rejected_and_same_order_retry_is_idempotent(): void
    {
        $order = $this->order();
        $this->rpc();
        $this->submit($order)->assertOk();
        $this->postJson('/demo-orders/'.$order->id.'/submit')->assertOk()->assertJsonPath('signature', $order->fresh()->signature);
        $other = $this->order(['id' => (string) Str::uuid(), 'product_id' => Product::skip(1)->first()->id]);
        $wire = base64_encode(str_replace($order->id, $other->id, base64_decode($this->fixture['wire'])));
        $this->postJson('/demo-orders/'.$other->id.'/submit', ['transaction' => $wire])->assertStatus(409)->assertJsonPath('error', 'duplicateSignature');
        $this->assertNull($other->fresh()->signature);
    }

    public function test_rpc_timeout_preserves_signature_for_safe_recovery(): void
    {
        $order = $this->order();
        Http::fake(fn ($request) => $request['method'] === 'getGenesisHash'
            ? Http::response(['result' => DevnetRpc::GENESIS]) : throw new \Illuminate\Http\Client\ConnectionException('Timeout'));
        $this->submit($order)->assertStatus(503)->assertJsonPath('error', 'rpcUnavailable');
        $this->assertNotNull($order->fresh()->signature);
        $this->assertSame('pending', $order->fresh()->status);
        $this->rpc($this->chainResult());
        $this->postJson('/demo-orders/'.$order->id.'/verify')->assertOk()->assertJsonPath('status', 'confirmed');
    }

    public function test_expired_unobserved_signature_can_only_close_after_finalized_height_and_history_check(): void
    {
        $order = $this->order();
        $this->rpc();
        $this->submit($order)->assertOk();
        $this->rpc(overrides: ['getBlockHeight' => 501]);
        $this->postJson('/demo-orders/'.$order->id.'/verify')->assertOk()->assertJsonPath('status', 'failed')->assertJsonPath('failure_reason', 'expired');
        $this->assertNull($order->fresh()->verified_at);
    }

    public function test_no_signature_or_malformed_payer_cannot_be_verified_as_a_payment(): void
    {
        $order = $this->order();
        $this->postJson('/demo-orders/'.$order->id.'/verify')->assertOk()->assertJsonPath('status', 'pending');
        $this->postJson(route('demo-orders.store', $this->product), ['payer' => ['invalid']])->assertStatus(422);
        $this->postJson(route('demo-orders.store', $this->product), ['payer' => 'not-a-key'])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_pending_order_is_reused_and_amount_recipient_snapshots_are_immutable(): void
    {
        $order = $this->order();
        $this->rpc();
        $this->submit($order)->assertOk();
        config(['solana.demo_lamports' => 2000000]);
        $this->postJson(route('demo-orders.store', $this->product), ['payer' => $this->fixture['payer']])
            ->assertOk()->assertJsonPath('id', $order->id)->assertJsonPath('lamports', 1000000);
        $this->assertDatabaseCount('demo_orders', 1);
    }

    public function test_old_or_different_finalized_transactions_never_verify(): void
    {
        $order = $this->order();
        $this->rpc();
        $this->submit($order)->assertOk();
        foreach ([
            ['blockTime' => now()->subHour()->timestamp],
            ['transaction' => [base64_encode("\x01".str_repeat("\x02", 64).substr(base64_decode($this->fixture['wire']), 65)), 'base64']],
            ['meta' => ['fee' => 5001]],
        ] as $change) {
            $this->rpc($this->chainResult($change));
            $this->postJson('/demo-orders/'.$order->id.'/verify')->assertStatus(422)->assertJsonPath('error', 'invalidTransaction');
            $this->assertNull($order->fresh()->verified_at);
        }
    }

    public function test_preflight_rejection_is_reported_but_signature_is_retained_until_checked(): void
    {
        $order = $this->order();
        Http::fake(fn ($request) => Http::response($request['method'] === 'getGenesisHash'
            ? ['result' => DevnetRpc::GENESIS]
            : ['error' => ['code' => -32002, 'message' => 'Transaction simulation failed']]));
        $this->submit($order)->assertStatus(422)->assertJsonPath('error', 'submissionRejected');
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->signature);
        $this->assertNull($order->fresh()->verified_at);
    }

    public function test_german_demo_ui_and_disabled_default_are_clear_and_preserve_eur_price(): void
    {
        $this->withSession(['locale' => 'de'])->get(route('products.show', $this->product))->assertOk()
            ->assertSee('Mit Test-SOL bezahlen')->assertSee('NUR DEVNET')->assertSee('0,001 Test SOL')->assertSee('24,00 €');
        config(['solana.demo_recipient' => null]);
        $this->get(route('products.show', $this->product))->assertOk()->assertSee('Demozahlungen sind erst verfügbar');
    }
}
