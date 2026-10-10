<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_are_sent_to_the_sign_in_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_active_staff_reach_the_back_office(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertOk();
    }

    public function test_deactivated_staff_are_refused(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => false]))->get('/admin')->assertForbidden();
    }
}
