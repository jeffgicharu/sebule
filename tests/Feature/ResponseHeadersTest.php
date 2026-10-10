<?php

namespace Tests\Feature;

use Tests\TestCase;

class ResponseHeadersTest extends TestCase
{
    public function test_the_home_page_loads(): void
    {
        $this->get('/')->assertOk()->assertSee(config('app.name'));
    }

    public function test_responses_carry_the_release_when_one_is_set(): void
    {
        config(['store.release' => 'abc1234']);

        $this->get('/')->assertHeader('X-Release', 'abc1234');
    }

    public function test_responses_omit_the_release_when_none_is_set(): void
    {
        config(['store.release' => null]);

        $this->get('/')->assertHeaderMissing('X-Release');
    }

    public function test_search_engines_are_asked_not_to_index_by_default(): void
    {
        config(['store.allow_indexing' => false]);

        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_indexing_can_be_allowed(): void
    {
        config(['store.allow_indexing' => true]);

        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_the_back_office_asks_staff_to_sign_in(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
