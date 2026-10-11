<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * What staff have changed about how the store runs and what its home
         * page says. A setting with no row here keeps the value the code ships with.
         */
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->jsonb('value')->nullable();
            $table->timestamps();
        });

        // The store's own pages: about, delivery, returns and the rest. The body is Markdown.
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('summary', 300)->nullable();
            $table->text('body');
            // Which list in the footer links to the page, if any.
            $table->string('footer_group', 20)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['footer_group', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::dropIfExists('settings');
    }
};
