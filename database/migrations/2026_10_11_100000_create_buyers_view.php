<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Everybody who has placed an order, one row for each phone number.
         * Checkout needs no account, so the store knows a customer by the
         * number they gave. The name, email and marketing choice are the ones
         * on their latest order; "spent" counts orders paid for and not cancelled.
         */
        DB::statement(<<<'SQL'
            CREATE VIEW buyers AS
            SELECT
                phone,
                (array_agg(customer_name ORDER BY id DESC))[1] AS name,
                (array_agg(email ORDER BY id DESC) FILTER (WHERE email IS NOT NULL))[1] AS email,
                (array_agg(marketing_opt_in ORDER BY id DESC))[1] AS marketing_opt_in,
                count(*) AS orders_placed,
                count(*) FILTER (WHERE paid_at IS NOT NULL AND status <> 'cancelled') AS orders_paid,
                coalesce(sum(total) FILTER (WHERE paid_at IS NOT NULL AND status <> 'cancelled'), 0) AS spent,
                min(created_at) AS first_order_at,
                max(created_at) AS last_order_at
            FROM orders
            GROUP BY phone
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS buyers');
    }
};
