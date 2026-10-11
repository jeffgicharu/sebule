<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Every message the store writes to a customer, kept exactly as it was
         * written, whether or not it was sent. Staff read it to see what a
         * customer was told and when.
         */
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 10);
            // What the message is about, such as "paid" or "out_for_delivery".
            $table->string('kind', 40);
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->text('html')->nullable();
            $table->string('status', 20);
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'kind']);
            $table->index(['channel', 'created_at']);
            $table->index(['recipient', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
