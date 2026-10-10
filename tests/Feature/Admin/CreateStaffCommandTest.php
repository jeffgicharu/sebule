<?php

namespace Tests\Feature\Admin;

use App\Enums\StaffRole;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateStaffCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_account_and_prints_the_password_once(): void
    {
        $this->artisan('staff:create', ['name' => 'Wanjiru Kamau', 'email' => 'Wanjiru@Example.com', '--role' => 'manager'])
            ->expectsOutputToContain('Email: wanjiru@example.com')
            ->expectsOutputToContain('Password: ')
            ->assertSuccessful();

        $staff = User::where('email', 'wanjiru@example.com')->first();

        $this->assertSame('Wanjiru Kamau', $staff->name);
        $this->assertSame(StaffRole::Manager, $staff->role);
        $this->assertTrue($staff->is_active);
        $this->assertTrue(Hash::isHashed($staff->password));
    }

    public function test_it_refuses_an_address_that_is_already_used(): void
    {
        User::factory()->create(['email' => 'wanjiru@example.com']);

        $this->artisan('staff:create', ['name' => 'Wanjiru Kamau', 'email' => 'wanjiru@example.com'])
            ->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_it_refuses_an_unknown_role(): void
    {
        $this->artisan('staff:create', ['name' => 'Wanjiru Kamau', 'email' => 'wanjiru@example.com', '--role' => 'boss'])
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_the_category_seeder_can_run_twice_without_duplicates(): void
    {
        $this->seed(CategorySeeder::class);
        $count = Category::count();

        Category::where('slug', 'sofas')->update(['name' => 'Sofas and sectionals']);
        $this->seed(CategorySeeder::class);

        $this->assertSame($count, Category::count());
        $this->assertSame('Sofas and sectionals', Category::where('slug', 'sofas')->value('name'));
        $this->assertSame('Living room', Category::where('slug', 'sofas')->first()->parent->name);
        $this->assertSame(7, Category::topLevel()->count());
    }
}
