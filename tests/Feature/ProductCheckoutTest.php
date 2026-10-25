<?php

namespace Tests\Feature;

use App\Exceptions\DemoPaymentException;
use App\Models\DemoOrder;
use App\Models\Product;
use App\Services\DemoTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_is_only_on_product_pages_with_natural_labels_and_honest_demo_disclosure(): void
    {
        $this->seed();
        foreach (['/', '/products/create'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('data-product-checkout', false)
                ->assertDontSee('Connect Wallet')->assertDontSee('Demo checkout');
        }
        $product = Product::first();
        $response = $this->get(route('products.show', $product))->assertOk()
            ->assertSee('Buy Now')->assertSee('Payment Method')->assertSee('Solana')->assertSee($product->title)
            ->assertSee('Connect Wallet')->assertSee('Continue to Payment')->assertSee('€24.00')
            ->assertSee('Checkout confirmation')
            ->assertSee('Any payment here would use 0.001 Devnet test SOL.')
            ->assertSee('It would not pay for or purchase the EUR-listed item.')
            ->assertSee('data-checkout-continue disabled', false)->assertSee('recipient is checked')
            ->assertDontSee('Payment Confirmed')->assertDontSee('data-demo-payment', false)
            ->assertDontSee('checkout-disclosure', false)->assertDontSee('demonstration transaction');
        $response->assertDontSee('Devnet demo · 0.001 test SOL · no real purchase')
            ->assertDontSee('checkout-demo-note', false)->assertDontSee('checkout-price-note', false);
        $this->assertMatchesRegularExpression('/<details class="checkout-confirmation">.*?id="checkout-confirmation-note".*?<\/details>.*?data-checkout-continue disabled/s', $response->getContent());
        $response->assertSee('aria-describedby="checkout-confirmation-note checkout-unavailable"', false);
        preg_match_all('/<(?:h[1-6]|button)\b[^>]*>(.*?)<\/(?:h[1-6]|button)>/s', $response->getContent(), $majorText);
        foreach ($majorText[1] as $text) {
            $this->assertDoesNotMatchRegularExpression('/Test SOL|Devnet|MVP|Blockchain Testing/i', strip_tags($text));
        }
    }

    public function test_checkout_disclosures_and_buttons_are_localized(): void
    {
        $this->seed();
        $this->withSession(['locale' => 'de'])->get(route('products.show', Product::first()))->assertOk()
            ->assertSee('Jetzt kaufen')->assertSee('Zahlungsart')->assertSee('Solana')
            ->assertSee('Wallet verbinden')->assertSee('Weiter zur Zahlung')->assertSee('24,00 €')
            ->assertSee('Bestätigung vor der Zahlung')->assertSee('0,001 Devnet-Test-SOL')
            ->assertSee('nicht bezahlt oder gekauft')->assertSee('Empfängeradresse geprüft')
            ->assertDontSee('Devnet-Demo · 0,001 Test-SOL · kein echter Kauf')
            ->assertDontSee('Zahlung bestätigt');
    }

    public function test_transfer_routes_remain_disabled_and_visiting_checkout_does_not_create_orders_or_call_rpc(): void
    {
        $this->seed();
        Http::preventStrayRequests();
        $this->get(route('products.show', Product::first()))->assertOk();
        $this->postJson('/products/1/demo-orders', ['payer' => '11111111111111111111111111111111'])->assertNotFound();
        $this->assertDatabaseCount('demo_orders', 0);
        $this->assertFalse(config('solana.payments_enabled'));
        $this->assertTrue(config('solana.presentation_only'));
        Http::assertNothingSent();
    }

    public function test_preserved_backend_parser_rejects_mismatched_recipient_amount_and_memo(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/support/demo-transaction.json')), true);
        $order = new DemoOrder([
            'id' => $fixture['id'], 'payer' => $fixture['payer'], 'recipient' => $fixture['recipient'],
            'lamports' => $fixture['lamports'], 'blockhash' => $fixture['blockhash'],
        ]);
        $parser = app(DemoTransaction::class);
        $this->assertNotEmpty($parser->inspect($fixture['wire'], $order)['signature']);
        foreach (['recipient' => $fixture['payer'], 'lamports' => 1000001, 'id' => '00000000-0000-4000-8000-000000000002'] as $field => $value) {
            $changed = clone $order;
            $changed->$field = $value;
            try { $parser->inspect($fixture['wire'], $changed); $this->fail('Mismatch must be rejected.'); }
            catch (DemoPaymentException $exception) { $this->assertSame('invalidTransaction', $exception->reason); }
        }
    }
}
