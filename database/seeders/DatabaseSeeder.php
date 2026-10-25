<?php
namespace Database\Seeders;
use App\Models\Product;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder {
    public function run(): void {
        $samples = [
            ['Everyday campus backpack', 'A roomy olive backpack with a padded laptop sleeve. Used for one semester, clean and ready for its next adventure.', 24, 'Clothing', 'Leonie', 'backpack'],
            ['Calculus, minus the full price', 'Calculus textbook with clear worked examples. Some pencil notes, all pages intact. Perfect for first-year study sessions.', 18, 'Books', 'Jonas', 'books'],
            ['A little light for late nights', 'Adjustable desk lamp in warm cream. Works perfectly and makes a small study corner feel like home.', 15, 'Furniture', 'Mila', 'lamp'],
            ['Your new study soundtrack', 'Comfortable over-ear headphones with a wired connection. Great sound, includes cable. Light wear on the headband.', 45, 'Electronics', 'Alex', 'headphones'],
            ['Coffee between lectures', 'Sage ceramic coffee set with two cups and saucers. No chips, just looking for a new kitchen.', 12, 'Other', 'Sara', 'coffee'],
            ['Take the scenic route', 'A sturdy city bicycle with a basket, working lights and recently checked brakes. Best suited for riders around 165 to 180 cm.', 95, 'Sports', 'Ben', 'bike'],
        ];
        foreach ($samples as [$title, $description, $price, $category, $seller, $image]) {
            [$district, $condition, $pickup] = \App\Support\BerlinSamples::DETAILS[$image];
            $product = Product::firstOrCreate(['image_path' => 'samples/'.$image.'.svg'], [
                'title' => $title, 'description' => $description, 'price' => $price,
                'category' => $category, 'seller_name' => $seller,
                'district' => $district, 'condition' => $condition, 'pickup_details' => $pickup,
            ]);
            // Only fill missing metadata on existing samples; never overwrite edits.
            foreach (['district' => $district, 'condition' => $condition, 'pickup_details' => $pickup] as $field => $value) {
                if ($product->$field === null) { $product->$field = $value; }
            }
            $product->save();
        }
    }
}
