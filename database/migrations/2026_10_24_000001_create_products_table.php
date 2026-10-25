<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->string('category', 30)->index();
            $table->string('seller_name', 80);
            $table->string('image_path');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('products'); }
};
