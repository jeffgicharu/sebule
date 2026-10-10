<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The zones the store opens with and the counties in each. Staff change the
     * fees and move counties between zones afterwards; the counties themselves are fixed.
     * Fees are in whole shillings here and stored in cents.
     *
     * @var array<string, array{fee: int, days: array{0: int, 1: int}, counties: list<string>}>
     */
    private const ZONES = [
        'Nairobi' => ['fee' => 1500, 'days' => [1, 3], 'counties' => ['Nairobi']],
        'Around Nairobi' => ['fee' => 2500, 'days' => [2, 4], 'counties' => ['Kiambu', 'Machakos', 'Kajiado']],
        'Central and Rift Valley' => ['fee' => 4500, 'days' => [3, 6], 'counties' => [
            'Nakuru', 'Nyeri', "Murang'a", 'Kirinyaga', 'Nyandarua', 'Embu', 'Meru', 'Tharaka-Nithi', 'Laikipia', 'Narok',
            'Uasin Gishu', 'Kericho', 'Bomet', 'Nandi', 'Baringo', 'Elgeyo-Marakwet', 'Makueni', 'Kitui',
        ]],
        'Coast' => ['fee' => 6500, 'days' => [4, 7], 'counties' => ['Mombasa', 'Kilifi', 'Kwale', 'Taita-Taveta']],
        'Western and Nyanza' => ['fee' => 6500, 'days' => [4, 7], 'counties' => [
            'Kisumu', 'Kakamega', 'Bungoma', 'Busia', 'Vihiga', 'Siaya', 'Homa Bay', 'Migori', 'Kisii', 'Nyamira', 'Trans Nzoia',
        ]],
        'Northern and far counties' => ['fee' => 9500, 'days' => [7, 10], 'counties' => [
            'Turkana', 'West Pokot', 'Samburu', 'Marsabit', 'Isiolo', 'Mandera', 'Wajir', 'Garissa', 'Tana River', 'Lamu',
        ]],
    ];

    public function up(): void
    {
        // An area the store delivers to at one fee.
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedBigInteger('fee');
            $table->unsignedSmallInteger('days_min');
            $table->unsignedSmallInteger('days_max');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // Kenya's 47 counties. A county with no zone is one the store does not deliver to.
        Schema::create('counties', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        $position = 0;

        foreach (self::ZONES as $name => $zone) {
            $zoneId = DB::table('delivery_zones')->insertGetId([
                'name' => $name,
                'fee' => $zone['fee'] * 100,
                'days_min' => $zone['days'][0],
                'days_max' => $zone['days'][1],
                'position' => ++$position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($zone['counties'] as $county) {
                DB::table('counties')->insert([
                    'name' => $county,
                    'delivery_zone_id' => $zoneId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('counties');
        Schema::dropIfExists('delivery_zones');
    }
};
