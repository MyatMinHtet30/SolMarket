<?php

namespace Tests\Feature;

use App\Exceptions\DemoPaymentException;
use App\Http\Controllers\DemoPaymentController;
use App\Models\DemoOrder;
use App\Models\Product;
use App\Services\DemoPayment;
use App\Services\DevnetRpc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PresentationMvpTest extends TestCase
{
    use RefreshDatabase;

    private function historicalOrder(): DemoOrder
    {
        $this->seed();
        $fixture = json_decode(file_get_contents(base_path('tests/support/demo-transaction.json')), true);

        return DemoOrder::create([
            'id' => $fixture['id'], 'product_id' => Product::first()->id,
            'session_hash' => hash('sha256', 'previous-demo'), 'status' => 'confirmed',
            'payer' => $fixture['payer'], 'recipient' => $fixture['recipient'], 'lamports' => 1000000,
            'blockhash' => $fixture['blockhash'], 'last_valid_block_height' => 500,
            'signature' => str_repeat('1', 64), 'signed_transaction' => $fixture['wire'],
            'expires_at' => now()->subHour(), 'verified_at' => now()->subHour(),
        ]);
    }

    public function test_detail_pages_keep_marketplace_details_and_block_legacy_payment_actions(): void
    {
        $this->seed();
        $product = Product::first();
        foreach (['en', 'de'] as $locale) {
            $response = $this->withSession(['locale' => $locale])->get(route('products.show', $product))
                ->assertOk()->assertSee($product->title)->assertSee($product->district)
                ->assertSee($product->pickup_details)->assertDontSee('data-wallet', false);
            foreach (['data-demo-payment', 'data-pay', 'data-retry', 'data-payment-status',
                'data-receipt', 'data-explorer', 'Pay with Test SOL', 'Mit Test-SOL bezahlen',
                'explorer.solana.com', 'ON THE HORIZON', 'Zahlungen · kommen später'] as $removed) {
                $response->assertDontSee($removed, false);
            }
        }
    }

    public function test_all_previous_payment_endpoints_return_404_and_preserve_old_records(): void
    {
        $order = $this->historicalOrder();
        $before = $order->fresh()->getRawOriginal();
        Http::preventStrayRequests();

        $this->postJson('/products/'.$order->product_id.'/demo-orders', ['payer' => $order->payer])->assertNotFound();
        $this->getJson('/demo-orders/'.$order->id)->assertNotFound();
        foreach (['submit', 'verify', 'cancel'] as $action) {
            $this->postJson('/demo-orders/'.$order->id.'/'.$action)->assertNotFound();
        }
        foreach (['store', 'show', 'submit', 'verify', 'cancel'] as $action) {
            $this->assertFalse(Route::has('demo-orders.'.$action));
        }
        $this->assertSame($before, $order->fresh()->getRawOriginal());
        $this->assertDatabaseCount('products', 6);
        $this->assertDatabaseCount('demo_orders', 1);
        Http::assertNothingSent();
    }

    public function test_payment_environment_settings_are_not_active_and_retained_services_cannot_submit(): void
    {
        $order = $this->historicalOrder();
        $defaults = require config_path('solana.php');
        $this->assertTrue($defaults['presentation_only']);
        $this->assertFalse($defaults['enabled']);
        $this->assertFalse($defaults['payments_enabled']);
        $this->assertNull($defaults['demo_recipient']);
        $this->assertSame(1000000, $defaults['demo_lamports']);
        // Even accidental runtime re-enabling cannot bypass the presentation guard.
        config(['solana.payments_enabled' => true, 'solana.demo_recipient' => $order->recipient, 'solana.demo_lamports' => 1000000]);
        $this->assertFalse(DemoPayment::configured());
        Http::preventStrayRequests();
        foreach ([
            fn () => app(DemoPayment::class)->submit($order, $order->signed_transaction),
            fn () => app(DemoPayment::class)->verify($order),
            fn () => app(DevnetRpc::class)->call('sendTransaction', [$order->signed_transaction]),
        ] as $operation) {
            try { $operation(); $this->fail('Retired actions must stay disabled.'); }
            catch (DemoPaymentException $exception) { $this->assertSame('paymentsDisabled', $exception->reason); }
        }
        Route::post('/retired-action-test', [DemoPaymentController::class, 'store']);
        $this->postJson('/retired-action-test')->assertNotFound();
        Http::assertNothingSent();
        $this->assertSame('confirmed', $order->fresh()->status);
    }
}
