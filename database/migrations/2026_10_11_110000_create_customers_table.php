<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * A customer's account. Nobody needs one to buy: it keeps addresses for
         * next time, shows past orders in one place and carries a cart from one
         * device to another. Staff sign in elsewhere and are never in this table.
         */
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            // Asked separately from everything else and off until the customer says yes.
            $table->boolean('marketing_opt_in')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('county_id')->constrained()->restrictOnDelete();
            $table->string('town');
            $table->string('estate');
            $table->string('house')->nullable();
            $table->text('directions')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('customer_password_resets', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // An order placed while signed in belongs to that account. A guest's order belongs to nobody.
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('checkout_key')->constrained()->nullOnDelete();
        });

        // A signed-in customer has one cart, which is how it follows them to another device.
        Schema::table('carts', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->unique()->after('token')->constrained()->cascadeOnDelete();
        });

        // A message that is not about an order, such as a password reset, is kept against the account.
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('order_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));
        Schema::table('carts', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));
        Schema::dropIfExists('customer_password_resets');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
