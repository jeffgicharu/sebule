<?php

namespace Tests\Feature\Admin;

use App\Enums\StaffRole;
use App\Enums\StockMovementReason;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Inventory\StockLedger;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class StaffAndStockHistoryScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => StaffRole::Owner]);
        $this->actingAs($this->owner);
    }

    public function test_stock_history_lists_each_change_with_who_and_why(): void
    {
        $variant = ProductVariant::factory()->create(['sku' => 'AMANI-SAND']);
        $ledger = new StockLedger;
        $received = $ledger->adjust($variant, 8, StockMovementReason::Received, 'First delivery', $this->owner);
        $damaged = $ledger->adjust($variant, -1, StockMovementReason::Damaged, 'Torn in transit', $this->owner);

        $this->get(StockMovementResource::getUrl('index'))
            ->assertOk()
            ->assertSee('First delivery')
            ->assertSee('Torn in transit')
            ->assertSee('+8')
            ->assertSee('-1')
            ->assertSee($this->owner->name);

        Livewire::test(ListStockMovements::class)
            ->assertCanSeeTableRecords([$damaged, $received], inOrder: true);
    }

    public function test_stock_history_can_be_narrowed_to_one_variant_from_its_link(): void
    {
        $sand = ProductVariant::factory()->create(['sku' => 'AMANI-SAND']);
        $clay = ProductVariant::factory()->create(['sku' => 'AMANI-CLAY']);
        $ledger = new StockLedger;
        $forSand = $ledger->adjust($sand, 3, StockMovementReason::Received);
        $forClay = $ledger->adjust($clay, 5, StockMovementReason::Received);

        Livewire::withQueryParams(['search' => 'AMANI-SAND'])
            ->test(ListStockMovements::class)
            ->assertCanSeeTableRecords([$forSand])
            ->assertCanNotSeeTableRecords([$forClay]);
    }

    public function test_stock_history_cannot_be_added_to_or_changed_by_hand(): void
    {
        $this->assertFalse(StockMovementResource::canCreate());
        $this->assertArrayNotHasKey('create', StockMovementResource::getPages());
        $this->assertArrayNotHasKey('edit', StockMovementResource::getPages());
    }

    public function test_an_owner_can_add_a_staff_member(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Wanjiru Kamau',
                'email' => 'wanjiru@example.com',
                'role' => StaffRole::Fulfilment,
                'password' => 'a-long-enough-password',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $staff = User::where('email', 'wanjiru@example.com')->first();

        $this->assertSame(StaffRole::Fulfilment, $staff->role);
        $this->assertTrue($staff->is_active);
        $this->assertTrue(Hash::check('a-long-enough-password', $staff->password));
    }

    public function test_a_staff_password_must_be_long_and_the_email_unused(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Otieno', 'email' => $this->owner->email, 'role' => StaffRole::Support, 'password' => 'short'])
            ->call('create')
            ->assertHasFormErrors(['email', 'password']);
    }

    public function test_editing_staff_without_a_new_password_keeps_the_old_one(): void
    {
        $staff = User::factory()->create(['password' => 'the-original-password', 'role' => StaffRole::Support]);

        Livewire::test(EditUser::class, ['record' => $staff->getRouteKey()])
            ->fillForm(['role' => StaffRole::Manager, 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $staff->refresh();

        $this->assertSame(StaffRole::Manager, $staff->role);
        $this->assertFalse($staff->is_active);
        $this->assertTrue(Hash::check('the-original-password', $staff->password));
    }

    public function test_owners_cannot_lock_themselves_out(): void
    {
        Livewire::test(EditUser::class, ['record' => $this->owner->getRouteKey()])
            ->assertFormFieldDisabled('role')
            ->assertFormFieldDisabled('is_active');
    }

    public function test_staff_accounts_are_switched_off_rather_than_deleted(): void
    {
        $staff = User::factory()->create();

        $this->assertFalse(UserResource::canDelete($staff));

        Livewire::test(EditUser::class, ['record' => $staff->getRouteKey()])
            ->assertActionDoesNotExist(DeleteAction::class);
    }

    public function test_owners_see_the_staff_screens(): void
    {
        $this->get('/admin')->assertOk()->assertSee(UserResource::getUrl('index'), escape: false);
        $this->get(UserResource::getUrl('index'))->assertOk();

        Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$this->owner]);
    }

    public function test_other_roles_neither_see_nor_reach_the_staff_screens(): void
    {
        $this->actingAs(User::factory()->create(['role' => StaffRole::Manager]));

        $this->get('/admin')->assertOk()->assertDontSee(UserResource::getUrl('index'), escape: false);
        $this->get(UserResource::getUrl('index'))->assertForbidden();
        $this->get(UserResource::getUrl('create'))->assertForbidden();
    }
}
