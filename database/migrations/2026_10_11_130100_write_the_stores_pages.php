<?php

use Database\Seeders\PageSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * A store is not finished without its delivery, returns and privacy
     * pages, so they arrive with the tables that hold them. Staff edit them
     * from there on.
     */
    public function up(): void
    {
        (new PageSeeder)->run();
    }
};
