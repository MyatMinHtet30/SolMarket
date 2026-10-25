<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public const CATEGORIES = ['Electronics', 'Books', 'Furniture', 'Clothing', 'Sports', 'Other'];
    public const DISTRICTS = ['Friedrichshain', 'Kreuzberg', 'Mitte', 'Neukölln', 'Charlottenburg', 'Lichtenberg'];
    public const CONDITIONS = ['Neu', 'Wie neu', 'Gut', 'Gebraucht'];
    protected $fillable = ['title', 'description', 'price', 'category', 'seller_name', 'image_path', 'district', 'condition', 'pickup_details'];
    protected function casts(): array { return ['price' => 'decimal:2']; }
    public function imageUrl(): string
    {
        return asset(str_starts_with($this->image_path, 'samples/') ? $this->image_path : 'storage/'.$this->image_path);
    }

    public function formattedPrice(): string
    {
        return app()->getLocale() === 'de'
            ? number_format((float) $this->price, 2, ',', '.').' €'
            : '€'.number_format((float) $this->price, 2);
    }
}
