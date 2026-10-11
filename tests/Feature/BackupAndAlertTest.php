<?php

namespace Tests\Feature;

use App\Console\Commands\BackUp;
use App\Models\Message;
use App\Support\Alert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * The copies kept in case the worst happens, and the alarm that is raised when something goes wrong.
 */
class BackupAndAlertTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('app/private/backups');
        File::deleteDirectory($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_the_backup_is_a_copy_of_the_database_that_holds_what_the_database_holds(): void
    {
        Message::create(['channel' => 'email', 'kind' => 'paid', 'recipient' => 'a-marker-for-the-backup@example.com', 'subject' => 'A marker', 'body' => 'Marker', 'status' => 'not_sent']);
        // The test's own transaction hides the row from pg_dump, so the copy is checked for the tables themselves.
        $this->artisan('store:backup')->expectsOutputToContain('Database: database-')->assertSuccessful();

        $copies = File::glob($this->directory.'/database-*.sql.gz');
        $this->assertCount(1, $copies);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($copies[0])), -4));

        $sql = gzdecode(file_get_contents($copies[0]));

        foreach (['orders', 'payments', 'products', 'customers', 'pages', 'settings'] as $table) {
            $this->assertStringContainsString("CREATE TABLE public.{$table} (", $sql);
        }

        $this->assertStringContainsString('CREATE VIEW public.buyers', $sql);
        $this->assertStringNotContainsString((string) config('database.connections.pgsql.password'), $sql);
    }

    public function test_only_the_newest_copies_are_kept(): void
    {
        File::ensureDirectoryExists($this->directory);

        foreach (range(1, 20) as $day) {
            File::put($this->directory.'/database-2026-09-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT).'-0215.sql.gz', 'old');
        }

        $this->artisan('store:backup')->assertSuccessful();

        $kept = collect(File::glob($this->directory.'/database-*.sql.gz'))->map(fn (string $file) => basename($file))->sort()->values();

        $this->assertCount(BackUp::KEEP_DATABASE, $kept);
        $this->assertSame('database-2026-09-08-0215.sql.gz', $kept->first());
        $this->assertStringStartsWith('database-'.now()->format('Y-m-d'), $kept->last());
    }

    public function test_the_photos_are_packed_when_asked_for(): void
    {
        File::ensureDirectoryExists(storage_path('app/public'));

        $this->artisan('store:backup --photos')->expectsOutputToContain('Photos: photos-')->assertSuccessful();

        $this->assertCount(1, File::glob($this->directory.'/photos-*.tar.gz'));
    }

    public function test_a_backup_that_fails_says_so_and_leaves_no_empty_copy_behind(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'pg_dump: error: connection to server failed', exitCode: 1)]);

        try {
            $this->artisan('store:backup')->run();
            $this->fail('A failed backup was reported as done.');
        } catch (RuntimeException $problem) {
            $this->assertStringContainsString('The database could not be backed up: pg_dump: error: connection to server failed', $problem->getMessage());
        }

        $this->assertSame([], File::glob($this->directory.'/database-*.sql.gz'));
    }

    public function test_an_error_on_a_page_is_emailed_to_whoever_looks_after_the_store_once_in_a_while(): void
    {
        config(['store.alerts.email' => 'keeper@sebule.test', 'app.debug' => false]);
        Route::get('/till/breaks', fn () => throw new RuntimeException('The till is on fire.'));

        // Three errors of one kind in a minute are one email, not three.
        Mail::shouldReceive('raw')->once()->withArgs(fn (string $text) => str_contains($text, 'The till is on fire.') && str_contains($text, 'During: GET till/breaks'));

        $this->get('/till/breaks')->assertStatus(500);
        $this->get('/till/breaks')->assertStatus(500);
        $this->get('/till/breaks')->assertStatus(500);
    }

    public function test_an_alert_names_the_trouble_and_where_it_happened(): void
    {
        config(['store.alerts.email' => 'keeper@sebule.test']);
        $sent = [];
        Mail::shouldReceive('raw')->once()->andReturnUsing(function (string $text) use (&$sent): void {
            $sent[] = $text;
        });

        Alert::forException(new RuntimeException('The till is on fire.'));

        $this->assertStringContainsString('Error: RuntimeException', $sent[0]);
        $this->assertStringContainsString('The till is on fire.', $sent[0]);
        $this->assertStringContainsString('tests/Feature/BackupAndAlertTest.php', $sent[0]);
        $this->assertStringContainsString('Site: '.config('app.url'), $sent[0]);
    }

    public function test_with_no_address_an_alert_is_written_to_the_log_and_nothing_is_sent(): void
    {
        config(['store.alerts.email' => null]);
        Mail::fake();
        Log::shouldReceive('critical')->once()->with('The backup did not run', ['detail' => 'Disk full']);

        Alert::raise('The backup did not run', 'Disk full');

        Mail::assertNothingSent();
    }

    public function test_things_that_are_not_faults_raise_no_alert(): void
    {
        config(['store.alerts.email' => 'keeper@sebule.test']);
        Mail::fake();

        $this->get('/no-such-page')->assertNotFound();
        $this->get('/account')->assertRedirect();
        $this->post(route('account.login.store'), [])->assertSessionHasErrors();

        Mail::assertNothingSent();
    }
}
