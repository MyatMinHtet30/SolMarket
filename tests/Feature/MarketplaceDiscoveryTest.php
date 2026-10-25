<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_compact_controls_are_accessible_and_filters_work_without_javascript(): void
    {
        $this->seed();
        $response = $this->get('/')->assertOk()->assertSee('Good finds for your everyday')
            ->assertSee('6 finds')->assertSee('aria-controls="market-filter-panel"', false)
            ->assertSee('aria-expanded="false"', false)->assertSee('data-expand-label="Show filters"', false)
            ->assertSee('placeholder="Find your next favourite…"', false)->assertSee('aria-label="Search"', false);
        // The server leaves native GET controls usable; JS enhances them into a disclosure.
        preg_match('/<div id="market-filter-panel"([^>]*)>/', $response->getContent(), $matches);
        $this->assertStringNotContainsString('hidden', $matches[1]);
        $this->assertStringNotContainsString('disabled', $matches[1]);
        $response->assertSee('name="district"', false)->assertSee('name="condition"', false);
    }

    public function test_active_filters_selections_category_links_and_reset_preserve_get_behavior(): void
    {
        $this->seed();
        $response = $this->get('/?q=lamp&category=Furniture&district=Lichtenberg&condition=Wie%20neu')
            ->assertOk()->assertSee('1 find')->assertSee('A little light for late nights')
            ->assertDontSee('Take the scenic route')->assertSee('Search: lamp')->assertSee('Category: Furniture')
            ->assertSee('Berlin district: Lichtenberg')->assertSee('Condition: Like new')
            ->assertSee('name="category" value="Furniture"', false)
            ->assertSee('district=Lichtenberg', false)->assertSee('condition=Wie%20neu', false)
            ->assertSee('value="Lichtenberg" selected', false)->assertSee('value="Wie neu" selected', false)
            ->assertSee('Reset filters');
        $this->assertStringContainsString('class="market-reset" href="'.route('products.index').'#marketplace"', $response->getContent());
        $this->get('/?q=lamp&district=Kreuzberg')->assertOk()->assertSee('0 finds')->assertSee('No finds just yet.');
    }

    public function test_german_disclosure_active_filters_and_result_counts_are_localized(): void
    {
        $this->seed();
        $this->withSession(['locale' => 'de'])->get('/?q=lamp&category=Furniture&district=Lichtenberg&condition=Wie%20neu')
            ->assertOk()->assertSee('Gute Fundstücke für deinen Alltag')->assertSee('1 Fundstück')
            ->assertSee('aria-label="Filter anzeigen"', false)->assertSee('data-collapse-label="Filter ausblenden"', false)
            ->assertSee('placeholder="Finde dein Lieblingsstück…"', false)
            ->assertSee('Kategorie: Möbel')->assertSee('Berliner Bezirk: Lichtenberg')->assertSee('Zustand: Wie neu')
            ->assertSee('Filter zurücksetzen')->assertSee('Filter anwenden');
    }
}
