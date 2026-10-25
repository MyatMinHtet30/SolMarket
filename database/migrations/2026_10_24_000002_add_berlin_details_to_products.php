<?php

use App\Support\BerlinSamples;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('district', 40)->nullable()->index();
            $table->string('condition', 20)->nullable();
            $table->string('pickup_details', 500)->nullable();
        });

        foreach (BerlinSamples::DETAILS as $image => [$district, $condition, $pickup]) {
            DB::table('products')->where('image_path', 'samples/'.$image.'.svg')->update([
                'district' => $district, 'condition' => $condition, 'pickup_details' => $pickup,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['district']);
            $table->dropColumn(['district', 'condition', 'pickup_details']);
        });
    }
};
