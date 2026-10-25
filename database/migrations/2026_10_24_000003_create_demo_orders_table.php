<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('session_hash', 64);
            $table->string('status')->default('pending');
            $table->string('payer', 44);
            $table->string('recipient', 44);
            $table->unsignedBigInteger('lamports');
            $table->string('blockhash', 44);
            $table->unsignedBigInteger('last_valid_block_height');
            $table->string('signature', 88)->nullable()->unique();
            $table->text('signed_transaction')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
        // One in-flight demo per browser session/product, including ambiguous submissions.
        DB::statement("CREATE UNIQUE INDEX demo_orders_pending_unique ON demo_orders(session_hash, product_id) WHERE status = 'pending'");
    }

    public function down(): void { Schema::dropIfExists('demo_orders'); }
};
