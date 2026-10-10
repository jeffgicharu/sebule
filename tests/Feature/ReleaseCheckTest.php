<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Providers\AppServiceProvider;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The check a release must pass on the server before it is put live.
 */
class ReleaseCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CategorySeeder::class, CatalogueSeeder::class]);
        $this->withoutVite();
    }

    public function test_every_page_a_visitor_can_reach_loads(): void
    {
        // No limit to speak of: the whole shop is opened, one link at a time.
        $status = Artisan::call('release:check', ['--limit' => 5000]);
        $output = Artisan::output();

        $this->assertSame(0, $status, $output);
        $this->assertSame(1, preg_match('/Opened (\d+) pages and all of them loaded/', $output, $found), $output);

        // The rooms, their sub-categories, the collections and all eighty products, at the least.
        $this->assertGreaterThan(120, (int) $found[1]);
    }

    public function test_it_fails_when_what_was_cached_cannot_be_read_back(): void
    {
        Cache::put(AppServiceProvider::NAVIGATION, 'not a menu', now()->addHour());

        $this->artisan('release:check')
            ->expectsOutputToContain('did not load')
            ->assertFailed();
    }

    public function test_it_fails_when_a_link_leads_to_a_missing_page(): void
    {
        $this->get('/')->assertOk();

        // Switched off behind the menu's back, so the cached menu still links to it.
        Category::query()->where('slug', 'sofas')->toBase()->update(['is_active' => false]);

        $this->artisan('release:check')
            ->expectsOutputToContain('/c/sofas answered 404')
            ->assertFailed();
    }
}
