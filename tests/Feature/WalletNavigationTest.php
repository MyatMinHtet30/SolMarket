<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function assertMarketplaceOnly(string $locale): void
    {
        $this->seed();
        foreach (['/', '/products/create', route('products.show', Product::first())] as $url) {
            $response = $this->withSession(['locale' => $locale])->get($url)->assertOk()
                ->assertSee('solmarket')->assertSee('English')->assertSee('Deutsch')
                ->assertSee($locale === 'de' ? 'Artikel verkaufen' : 'Sell an item');
            preg_match('/<header\b[^>]*>.*?<\/header>/s', $response->getContent(), $header);
            foreach (['data-wallet', 'wallet-nav', 'Connect Wallet', 'Disconnect', 'Wallet verbinden',
                'Devnet', 'Testumgebung', 'Test Environment', 'phantom.com', 'on-chain transactions',
                'On-Chain-Transaktionen', 'data-demo-payment'] as $removed) {
                $this->assertStringNotContainsString($removed, $header[0]);
                if (in_array($url, ['/', '/products/create'], true)) {
                    $response->assertDontSee($removed, false);
                }
            }
        }
    }

    public function test_english_navigation_and_nonproduct_pages_remain_wallet_free(): void
    {
        $this->assertMarketplaceOnly('en');
        $this->assertFalse(config('solana.wallet_enabled'));
        $this->assertFalse(config('solana.enabled'));
    }

    public function test_german_navigation_and_nonproduct_pages_remain_wallet_free(): void
    {
        $this->assertMarketplaceOnly('de');
    }

    public function test_old_wallet_feature_flag_cannot_restore_removed_presentation_markup(): void
    {
        config(['solana.wallet_enabled' => true]);
        $this->assertMarketplaceOnly('en');
    }
}
