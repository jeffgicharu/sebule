<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The cache is emptied by each release, so that nothing cached by old code is
 * read by new code. Sessions must not go with it: a deploy should not sign
 * anybody out or lose a checkout in progress.
 */
class SessionsOutliveReleasesTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['APP_RELEASE'], $_SERVER['APP_RELEASE']);

        parent::tearDown();
    }

    public function test_sessions_are_kept_in_a_store_of_their_own(): void
    {
        $this->assertSame('sessions', config('session.store'));
        $this->assertSame('redis', config('cache.stores.sessions.driver'));
    }

    public function test_a_new_release_changes_the_cache_prefix_and_leaves_the_session_prefix_alone(): void
    {
        $prefixes = function (string $release): array {
            $_ENV['APP_RELEASE'] = $_SERVER['APP_RELEASE'] = $release;
            $cache = require config_path('cache.php');

            return [$cache['prefix'], $cache['stores']['sessions']['prefix']];
        };

        [$cacheBefore, $sessionsBefore] = $prefixes('1111111111111111111111111111111111111111');
        [$cacheAfter, $sessionsAfter] = $prefixes('2222222222222222222222222222222222222222');

        $this->assertStringEndsWith('111111111111:', $cacheBefore);
        $this->assertStringEndsWith('222222222222:', $cacheAfter);
        $this->assertSame($sessionsBefore, $sessionsAfter);
        $this->assertStringNotContainsString('1111', $sessionsBefore);
    }
}
