<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An ad-platform account a customer has connected to their licence.
 *
 * This exists so Google Ads can work from the WordPress plugin at all: that
 * channel needs an OAuth client secret and a developer token, and neither can
 * ship inside a GPL plugin whose source anyone can read. The OAuth happens
 * here instead, and uploads are made on the customer's behalf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_connections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('channel');

            // Everything the driver needs for this tenant: refresh token,
            // customer ID, conversion action. Encrypted at rest via the
            // model's `encrypted:array` cast - these are live credentials for
            // someone else's ad account.
            $table->text('credentials')->nullable();

            // Denormalised for display and support, never for auth.
            $table->string('account_id')->nullable();
            $table->string('account_name')->nullable();

            $table->string('status')->default('connected'); // connected, needs_reauth, revoked
            $table->text('last_error')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            // One connection per channel per licence.
            $table->unique(['license_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_connections');
    }
};
