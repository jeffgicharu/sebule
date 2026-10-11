<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

#[Signature('store:backup {--photos : Also pack the product photos, which change far less often than the database}')]
#[Description('Write a dated copy of the database, and of the photos when asked, and drop the copies that are past keeping')]
class BackUp extends Command
{
    /** How many database copies are kept: one a day for a fortnight. */
    public const KEEP_DATABASE = 14;

    /** How many copies of the photos are kept. */
    public const KEEP_PHOTOS = 2;

    public function handle(): int
    {
        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory, 0700);

        $database = $this->database($directory);
        $this->prune($directory, 'database-*.sql.gz', self::KEEP_DATABASE);
        $this->info('Database: '.basename($database).', '.$this->size($database));

        if ($this->option('photos')) {
            $photos = $this->photos($directory);
            $this->prune($directory, 'photos-*.tar.gz', self::KEEP_PHOTOS);
            $this->info('Photos: '.basename($photos).', '.$this->size($photos));
        }

        return self::SUCCESS;
    }

    /** A plain SQL copy of the whole database, compressed. It restores with psql into an empty database. */
    private function database(string $directory): string
    {
        $connection = config('database.connections.'.config('database.default'));
        $file = $directory.'/database-'.now()->format('Y-m-d-Hi').'.sql.gz';

        // pg_dump compresses the copy itself and is run without a shell, so there is no pipe whose failure could go unnoticed.
        $result = Process::timeout(600)
            // The password goes in the environment, never on the command line, where other users of the server could read it.
            ->env(['PGPASSWORD' => (string) $connection['password']])
            ->run([
                'pg_dump',
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--username='.$connection['username'],
                '--no-owner',
                '--no-privileges',
                '--compress=9',
                '--file='.$file,
                (string) $connection['database'],
            ]);

        if ($result->failed() || ! is_file($file) || filesize($file) < 1024) {
            File::delete($file);

            throw new RuntimeException('The database could not be backed up: '.trim($result->errorOutput() ?: 'pg_dump wrote nothing.'));
        }

        chmod($file, 0600);

        return $file;
    }

    private function photos(string $directory): string
    {
        $file = $directory.'/photos-'.now()->format('Y-m-d').'.tar.gz';

        $result = Process::timeout(1800)->run(['tar', '--create', '--gzip', '--file='.$file, '--directory='.storage_path('app'), 'public']);

        if ($result->failed()) {
            File::delete($file);

            throw new RuntimeException('The photos could not be backed up: '.trim($result->errorOutput()));
        }

        chmod($file, 0600);

        return $file;
    }

    /** Keep the newest copies and delete the rest. The names sort by date. */
    private function prune(string $directory, string $pattern, int $keep): void
    {
        $copies = File::glob($directory.'/'.$pattern);
        rsort($copies);

        File::delete(array_slice($copies, $keep));
    }

    private function size(string $file): string
    {
        $bytes = filesize($file);

        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : round($bytes / 1024).' KB';
    }
}
