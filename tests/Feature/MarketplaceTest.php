<?php
namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private function listing(array $overrides = []): array
    {
        // A real tiny PNG; does not require PHP's optional GD extension.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        return array_merge(['title' => 'Student desk', 'description' => 'A sturdy desk in excellent condition.', 'price' => '29.50', 'category' => 'Furniture', 'seller_name' => 'Sam', 'image' => UploadedFile::fake()->createWithContent('desk.png', $png)], $overrides);
    }

    public function test_homepage_shows_six_seeded_products_and_seeding_is_repeatable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('products', 6);
        $response = $this->get('/')->assertOk()->assertSee('Good finds.');
        foreach (Product::all() as $product) {
            $response->assertSee($product->title);
            $this->assertFileExists(public_path($product->image_path));
        }
    }

    public function test_search_category_and_combined_filters(): void
    {
        $this->seed();
        $this->get('/?q=lamp')->assertOk()->assertSee('A little light for late nights')->assertDontSee('Take the scenic route');
        $this->get('/?category=Sports')->assertOk()->assertSee('Take the scenic route')->assertDontSee('Coffee between lectures');
        $this->get('/?q=lamp&category=Furniture')->assertOk()->assertSee('A little light for late nights');
        $this->get('/?q=lamp&category=Books')->assertOk()->assertSee('No finds just yet.');
        $this->get('/?q=zzzzzzz')->assertOk()->assertSee('No finds just yet.');
    }

    public function test_detail_and_missing_product(): void
    {
        $this->seed();
        $product = Product::first();
        $this->get(route('products.show', $product))->assertOk()->assertSee($product->description)->assertSee($product->seller_name)->assertSee('24.00')->assertDontSee('data-wallet', false);
        $this->get('/products/99999')->assertNotFound();
        $this->get('/products/create')->assertOk()->assertSee('Publish your listing');
    }

    public function test_valid_listing_is_saved_with_image_and_redirects_to_detail(): void
    {
        Storage::fake('public');
        $response = $this->post('/products', $this->listing())->assertSessionHasNoErrors();
        $product = Product::sole();
        $response->assertRedirect(route('products.show', $product));
        $this->assertDatabaseHas('products', ['title' => 'Student desk', 'price' => 29.50, 'seller_name' => 'Sam']);
        Storage::disk('public')->assertExists($product->image_path);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Your listing is live.');
    }

    public function test_invalid_input_is_rejected_and_old_input_preserved(): void
    {
        Storage::fake('public');
        $this->from('/products/create')->post('/products', $this->listing(['price' => '-2', 'category' => 'Invalid', 'description' => 'short', 'seller_name' => '', 'image' => UploadedFile::fake()->create('unsafe.txt', 1, 'text/plain')]))
            ->assertRedirect('/products/create')->assertSessionHasErrors(['price', 'category', 'description', 'seller_name', 'image'])->assertSessionHasInput('title', 'Student desk');
        $this->assertDatabaseCount('products', 0);
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->post('/products', [])->assertSessionHasErrors(['title', 'description', 'price', 'category', 'seller_name', 'image']);
    }

    public function test_oversized_images_and_extra_price_decimals_are_rejected(): void
    {
        $this->post('/products', $this->listing(['price' => '12.345', 'image' => UploadedFile::fake()->create('large.png', 4097, 'image/png')]))->assertSessionHasErrors(['price', 'image']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_user_content_is_escaped_and_solana_remains_disabled(): void
    {
        Storage::fake('public');
        $this->post('/products', $this->listing(['title' => '<script>alert(1)</script>']));
        $this->get(route('products.show', Product::sole()))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertFalse(config('solana.enabled'));
    }

    public function test_pagination_preserves_filters(): void
    {
        for ($i = 0; $i < 14; $i++) {
            Product::create(['title' => 'Study desk '.$i, 'description' => 'A desk for studying', 'category' => 'Furniture', 'price' => 20, 'seller_name' => 'Sam', 'image_path' => 'samples/lamp.svg']);
        }
        $this->get('/?q=Study&category=Furniture')->assertOk()->assertSee('page=2', false)->assertSee('category=Furniture', false);
        $this->get('/?q=Study&category=Furniture&page=2')->assertOk()->assertSee('Study desk 0')->assertDontSee('Study desk 13');
    }
}
