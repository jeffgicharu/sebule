<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * The last gate before a release goes live. It runs inside the new release with the live
 * configuration, database and cache, starts at the home page and follows the links a
 * visitor could follow. Every page after the first reads back what the first one cached,
 * so data that does not survive the cache is caught here and not by a customer.
 */
#[Signature('release:check {--limit=80 : The most pages to open}')]
#[Description('Open the shop as a visitor would and fail if any page does not load')]
class CheckRelease extends Command
{
    /** Opened whether or not anything links to them. */
    private const ALWAYS = ['/', '/sitemap.xml', '/admin/login', '/up'];

    /** Links that lead out of the pages this command is here to check. */
    private const SKIPPED = ['//', '/admin', '/storage', '/build', '/images', '/livewire'];

    public function handle(Kernel $kernel): int
    {
        $limit = max(count(self::ALWAYS), (int) $this->option('limit'));
        $queue = self::ALWAYS;
        $opened = [];
        $failures = [];

        while ($queue !== [] && count($opened) < $limit) {
            $path = array_shift($queue);

            if (isset($opened[$path])) {
                continue;
            }

            $opened[$path] = true;
            [$body, $problem] = $this->open($kernel, $path);

            if ($problem !== null) {
                $failures[$path] = $problem;

                continue;
            }

            foreach ($this->links($body) as $link) {
                if (! isset($opened[$link]) && ! in_array($link, $queue, true)) {
                    $queue[] = $link;
                }
            }
        }

        // The home page a second time, now that everything it caches has been stored.
        [, $problem] = $this->open($kernel, '/');

        if ($problem !== null) {
            $failures['/ (second visit)'] = $problem;
        }

        foreach ($failures as $path => $problem) {
            $this->error("{$path} {$problem}");
        }

        $count = count($opened);

        if ($failures !== []) {
            $this->error(count($failures).' of '.$count.' pages did not load. The details are in the application log.');

            return self::FAILURE;
        }

        $this->info("Opened {$count} pages and all of them loaded.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string|null} the page, and what went wrong if anything did
     */
    private function open(Kernel $kernel, string $path): array
    {
        $request = Request::create(rtrim((string) config('app.url'), '/').$path);

        try {
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
        } catch (Throwable $exception) {
            return ['', 'failed with '.$this->describe($exception)];
        }

        $status = $response->getStatusCode();

        if ($status >= 400) {
            $exception = $response->exception ?? null;

            return ['', "answered {$status}".($exception ? ' after '.$this->describe($exception) : '')];
        }

        return [$status === 200 ? (string) $response->getContent() : '', null];
    }

    /**
     * The pages of this site that a page links to, as paths.
     *
     * @return list<string>
     */
    private function links(string $html): array
    {
        preg_match_all('/<a\s[^>]*?href="([^"#]+)/i', $html, $matches);

        $root = rtrim((string) config('app.url'), '/');

        return collect($matches[1])
            ->map(fn (string $href) => html_entity_decode($href))
            ->map(fn (string $href) => Str::startsWith($href, $root) ? Str::after($href, $root) : $href)
            ->filter(fn (string $href) => Str::startsWith($href, '/') && ! Str::startsWith($href, self::SKIPPED))
            ->unique()
            ->values()
            ->all();
    }

    /** Where it went wrong, without the message: this output is kept in the deploy log. */
    private function describe(Throwable $exception): string
    {
        return class_basename($exception).' at '.Str::after($exception->getFile(), base_path().'/').':'.$exception->getLine();
    }
}
