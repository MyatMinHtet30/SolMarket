<?php
namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request) {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', Rule::in(Product::CATEGORIES)],
            'district' => ['nullable', Rule::in(Product::DISTRICTS)],
            'condition' => ['nullable', Rule::in(Product::CONDITIONS)],
        ]);
        $query = Product::query();
        if ($search = trim($filters['q'] ?? '')) {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%'));
        }
        if ($category = $filters['category'] ?? null) { $query->where('category', $category); }
        foreach (['district', 'condition'] as $field) {
            if ($value = $filters[$field] ?? null) { $query->where($field, $value); }
        }
        return view('products.index', ['products' => $query->latest('id')->paginate(12)->withQueryString(), 'categories' => Product::CATEGORIES]);
    }
    public function create() { return view('products.create', ['categories' => Product::CATEGORIES]); }
    public function store(Request $request) {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:999999.99', 'decimal:0,2'],
            'category' => ['required', Rule::in(Product::CATEGORIES)],
            'seller_name' => ['required', 'string', 'max:80'],
            // Optional for compatibility with existing submissions and records.
            'district' => ['nullable', Rule::in(Product::DISTRICTS)],
            'condition' => ['nullable', Rule::in(Product::CONDITIONS)],
            'pickup_details' => ['nullable', 'string', 'max:500'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        $path = $request->file('image')->store('products', 'public');
        unset($data['image']);
        try { $product = Product::create([...$data, 'image_path' => $path]); }
        catch (\Throwable $e) { Storage::disk('public')->delete($path); throw $e; }
        return redirect()->route('products.show', $product)->with('success', __('Your listing is live. Nice one!'));
    }
    public function show(Product $product) { return view('products.show', compact('product')); }
}
