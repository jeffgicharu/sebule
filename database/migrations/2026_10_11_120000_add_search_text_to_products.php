<?php

use App\Catalogue\SearchIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trigram matching, so a search for "soffa" or "dinning table" still finds what was meant.
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        /*
         * Everything a customer might search a product by, in one lower-case
         * column: its name, category and room, collections, colours and
         * finishes, materials and description. Kept up to date as products change.
         */
        Schema::table('products', function (Blueprint $table) {
            $table->text('search_text')->default('');
        });

        DB::statement('CREATE INDEX products_search_text_trgm ON products USING gin (search_text gin_trgm_ops)');

        SearchIndex::rebuild();
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS products_search_text_trgm');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('search_text');
        });
    }
};
