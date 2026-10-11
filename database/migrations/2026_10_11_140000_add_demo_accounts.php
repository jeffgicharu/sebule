<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounts a visitor is let into to look around, on an installation that
     * allows it. They are marked, so what they may change can be limited.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('is_active');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('marketing_opt_in');
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('is_demo'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_demo'));
    }
};
