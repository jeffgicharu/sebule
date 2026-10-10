<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * An order keeps its own copy of everything it was placed with: who it is
         * for, where it goes and what each item was called and cost. Later changes
         * to the catalogue or the delivery fees never rewrite it. Money is in cents.
         */
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            // The secret in the link a customer follows to pay for and track the order.
            $table->string('token', 64)->unique();
            // Sent with the checkout form, so placing the same checkout twice gives one order.
            $table->uuid('checkout_key')->unique();
            $table->string('status');

            $table->string('customer_name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->boolean('marketing_opt_in')->default(false);

            $table->string('delivery_method');
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('zone_name')->nullable();
            $table->string('county')->nullable();
            $table->string('town')->nullable();
            $table->string('estate')->nullable();
            $table->string('house')->nullable();
            $table->text('directions')->nullable();
            $table->unsignedSmallInteger('delivery_days_min')->nullable();
            $table->unsignedSmallInteger('delivery_days_max')->nullable();

            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('delivery_fee');
            $table->unsignedBigInteger('total');
            // VAT is part of the total. The rate is in hundredths of a percent, so 16% is 1600.
            $table->unsignedInteger('vat_rate');
            $table->unsignedBigInteger('vat_amount');

            // Stock is held for the order until this time, and returned if it is still unpaid then.
            $table->timestamp('held_until')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'held_until']);
            $table->index('phone');
            $table->index('created_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_label')->nullable();
            $table->string('sku');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total');
            $table->boolean('made_to_order')->default(false);
            $table->unsignedSmallInteger('lead_time_weeks')->nullable();
            // Whether stock was taken for this line, so the same amount is returned if the order closes unpaid.
            $table->boolean('stock_taken')->default(false);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)');

        // What happened to an order and when: the timeline the customer follows and staff read.
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        /*
         * One attempt to pay for an order. "amount" is what the attempt settles on
         * the order; "charged" is what was actually asked of the customer's phone,
         * which differs only where the store is set to charge a token amount.
         */
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->string('state');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('charged');
            $table->string('phone', 20)->nullable();

            // M-Pesa's own references for the prompt and, once paid, the receipt.
            $table->string('merchant_request_id')->nullable();
            $table->string('checkout_request_id')->nullable()->unique();
            $table->string('receipt')->nullable()->unique();
            $table->string('result_code')->nullable();
            $table->string('result_description')->nullable();
            $table->jsonb('response')->nullable();

            $table->timestamp('checked_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'state']);
            $table->index(['state', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
