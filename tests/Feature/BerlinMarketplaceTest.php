<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BerlinMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private function listing(array $overrides = []): array
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');

        return array_merge([
            'title' => 'WG-Schreibtisch', 'description' => 'Ein stabiler Schreibtisch für deine WG.',
            'price' => '29.50', 'category' => 'Furniture', 'seller_name' => 'Sam',
            'district' => 'Neukölln', 'condition' => 'Gut', 'pickup_details' => 'U Hermannplatz, abends',
            'image' => UploadedFile::fake()->createWithContent('desk.png', $png),
        ], $overrides);
    }

    public function test_language_switch_persists_and_keeps_current_page_and_filters(): void
    {
        $this->seed();
        $returnTo = '/?q=lamp&category=Furniture&district=Lichtenberg';
        $this->post('/language/de', ['return_to' => $returnTo])
            ->assertStatus(303)->assertRedirect($returnTo)->assertSessionHas('locale', 'de');
        $this->get($returnTo)->assertOk()->assertHeader('Content-Language', 'de')->assertSee('<html lang="de">', false)
            ->assertSee('Dein Campus.')->assertSee('Dein Marktplatz.')
            ->assertSee('Berliner Bezirk')->assertSee('Abholung')->assertSee('15,00 €');
        $this->get('/products/create')->assertOk()->assertSee('Anzeige veröffentlichen')
            ->assertSee('Zustand')->assertSee('Wie neu')->assertSee('Angaben zur Abholung');
        $this->post('/language/en', ['return_to' => '/products/1'])->assertRedirect('/products/1');
        $this->get('/products/1')->assertOk()->assertSee('<html lang="en">', false)
            ->assertSee('Local pickup')->assertSee('€24.00');
    }

    public function test_locale_switch_rejects_unsupported_languages_and_external_redirects(): void
    {
        $this->post('/language/fr')->assertNotFound();
        foreach (['https://example.com', '//example.com', '/\\example.com', "/\r\nexample.com", ['invalid']] as $returnTo) {
            $this->post('/language/de', ['return_to' => $returnTo])->assertStatus(303)->assertRedirect('/');
        }
    }

    public function test_six_samples_have_distinct_berlin_districts_and_realistic_pickup_points(): void
    {
        $this->seed();
        $this->assertEqualsCanonicalizing(Product::DISTRICTS, Product::pluck('district')->all());
        foreach (Product::all() as $product) {
            $this->assertContains($product->condition, Product::CONDITIONS);
            $this->assertNotEmpty($product->pickup_details);
            $this->get(route('products.show', $product))->assertOk()
                ->assertSee($product->district)->assertSee($product->pickup_details);
        }
        $lamp = Product::where('image_path', 'samples/lamp.svg')->firstOrFail();
        $lamp->update(['pickup_details' => 'Custom pickup point']);
        $this->seed();
        $this->assertSame('Custom pickup point', $lamp->fresh()->pickup_details);
        $this->assertDatabaseCount('products', 6);
    }

    public function test_district_and_condition_filters_combine_with_search_and_category(): void
    {
        $this->seed();
        $this->get('/?district=Kreuzberg')->assertOk()->assertSee('Take the scenic route')->assertDontSee('Coffee between lectures');
        $this->get('/?q=lamp&category=Furniture&district=Lichtenberg&condition=Wie%20neu')
            ->assertOk()->assertSee('A little light for late nights')->assertDontSee('Take the scenic route');
        $this->get('/?q=lamp&district=Mitte')->assertOk()->assertSee('No finds just yet.');
        $this->get('/?condition=Gut')->assertOk()->assertSee('Your new study soundtrack')->assertDontSee('Coffee between lectures');
        $this->from('/')->get('/?district=Unknown')->assertRedirect('/')->assertSessionHasErrors('district');
        $this->from('/')->get('/?condition=Unknown')->assertRedirect('/')->assertSessionHasErrors('condition');
    }

    public function test_new_berlin_metadata_is_saved_and_rendered_in_german(): void
    {
        Storage::fake('public');
        $this->withSession(['locale' => 'de'])->post('/products', $this->listing())
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'Deine Anzeige ist online. Klasse!');
        $product = Product::sole();
        $this->assertDatabaseHas('products', ['district' => 'Neukölln', 'condition' => 'Gut', 'pickup_details' => 'U Hermannplatz, abends']);
        Storage::disk('public')->assertExists($product->image_path);
        $this->get(route('products.show', $product))->assertOk()->assertSee('Neukölln')
            ->assertSee('U Hermannplatz, abends')->assertSee('29,50 €')->assertSee('Zustand')
            ->assertDontSee('data-wallet', false);
        $this->assertFalse(config('solana.enabled'));
    }

    public function test_berlin_fields_are_validated_with_german_errors_and_preserved_input(): void
    {
        Storage::fake('public');
        $this->withSession(['locale' => 'de'])->from('/products/create')->post('/products', $this->listing([
            'district' => 'Hamburg', 'condition' => 'Broken', 'pickup_details' => str_repeat('x', 501), 'price' => '-1',
        ]))->assertRedirect('/products/create')->assertSessionHasErrors(['district', 'condition', 'pickup_details', 'price'])
            ->assertSessionHasInput('title', 'WG-Schreibtisch');
        $this->get('/products/create')->assertOk()->assertSee('Die Auswahl für Bezirk ist ungültig.')
            ->assertSee('Das Feld Preis muss mindestens 0.01 sein.')->assertSee('WG-Schreibtisch');
        $this->assertDatabaseCount('products', 0);
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_legacy_listings_do_not_gain_invented_locations_or_conditions(): void
    {
        $legacy = Product::create([
            'title' => 'Existing desk', 'description' => 'An existing listing with no location.',
            'price' => 20, 'category' => 'Furniture', 'seller_name' => 'Alex', 'image_path' => 'products/existing.jpg',
        ]);
        $this->get(route('products.show', $legacy))->assertOk()->assertSee('Location not specified')
            ->assertSee('Condition not specified')->assertSee('Details to be arranged');
        $this->seed();
        $this->assertNull($legacy->fresh()->district);
        $this->assertDatabaseCount('products', 7);
    }

    public function test_additive_migration_preserves_existing_record_values(): void
    {
        $migration = require database_path('migrations/2026_10_24_000002_add_berlin_details_to_products.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('products', 'district'));
        $legacy = Product::create([
            'title' => 'Keep my desk', 'description' => 'Preserve this exact description.',
            'price' => 42, 'category' => 'Furniture', 'seller_name' => 'Alex', 'image_path' => 'products/original.jpg',
        ]);
        $before = $legacy->getRawOriginal();
        $migration->up();
        $after = $legacy->fresh()->getRawOriginal();
        foreach ($before as $key => $value) { $this->assertSame($value, $after[$key]); }
        $this->assertNull($after['district']);
        $this->assertNull($after['condition']);
        $this->assertNull($after['pickup_details']);
    }

    public function test_pagination_and_category_links_keep_berlin_filters(): void
    {
        for ($i = 0; $i < 14; $i++) {
            Product::create([
                'title' => 'Study desk '.$i, 'description' => 'A desk for studying', 'price' => 20,
                'category' => 'Furniture', 'seller_name' => 'Sam', 'image_path' => 'samples/lamp.svg',
                'district' => 'Neukölln', 'condition' => 'Gut',
            ]);
        }
        $url = '/?q=Study&category=Furniture&district=Neuk%C3%B6lln&condition=Gut';
        $this->get($url)->assertOk()->assertSee('page=2', false)->assertSee('district=Neuk%C3%B6lln', false)->assertSee('condition=Gut', false);
        $this->get($url.'&page=2')->assertOk()->assertSee('Study desk 0')->assertDontSee('Study desk 13');
        $this->withSession(['locale' => 'de'])->get($url)->assertOk()->assertSee('Seitennavigation')
            ->assertSee('Weiter')->assertSee('Ergebnissen')->assertSee('Gehe zu Seite 2');
    }
}
