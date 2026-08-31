<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable()->index();
            $table->string('customer_id')->nullable()->index();
            $table->string('customer_email')->index();
            $table->string('customer_name')->nullable();
            $table->string('product_id')->nullable();
            $table->string('variant_id')->nullable();
            $table->string('tier')->default('pro'); // starter, pro, agency
            $table->string('license_key')->unique()->index();
            $table->string('status')->default('active'); // active, inactive, expired, disabled
            $table->unsignedInteger('activation_limit')->default(1);
            $table->unsignedInteger('activation_count')->default(0);
            $table->json('instances')->nullable(); // array of activated domain names
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
