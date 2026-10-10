<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementReason;
use App\Inventory\InsufficientStock;
use App\Inventory\StockLedger;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class StockLedgerTest extends TestCase
{
    use RefreshDatabase;

    private StockLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = new StockLedger;
    }

    public function test_receiving_stock_raises_the_balance_and_records_who_and_why(): void
    {
        $variant = ProductVariant::factory()->create();
        $staff = User::factory()->create();

        $movement = $this->ledger->adjust($variant, 12, StockMovementReason::Received, 'Delivery note 104', $staff);

        $this->assertSame(12, $variant->fresh()->stock_on_hand);
        $this->assertSame(12, $variant->stock_on_hand);
        $this->assertSame(12, $movement->quantity);
        $this->assertSame(12, $movement->balance_after);
        $this->assertSame(StockMovementReason::Received, $movement->reason);
        $this->assertSame('Delivery note 104', $movement->note);
        $this->assertTrue($movement->user->is($staff));
    }

    public function test_removing_stock_lowers_the_balance(): void
    {
        $variant = ProductVariant::factory()->withStock(5)->create();

        $movement = $this->ledger->adjust($variant, -2, StockMovementReason::Sold);

        $this->assertSame(3, $variant->fresh()->stock_on_hand);
        $this->assertSame(3, $movement->balance_after);
    }

    public function test_stock_cannot_go_below_zero(): void
    {
        $variant = ProductVariant::factory()->withStock(1)->create();

        try {
            $this->ledger->adjust($variant, -2, StockMovementReason::Sold);
            $this->fail('Selling more than is in stock should have been refused.');
        } catch (InsufficientStock $exception) {
            $this->assertSame(2, $exception->requested);
            $this->assertSame(1, $exception->available);
        }

        $this->assertSame(1, $variant->fresh()->stock_on_hand);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_the_last_item_can_be_taken_only_once(): void
    {
        $variant = ProductVariant::factory()->withStock(1)->create();

        $this->ledger->adjust($variant, -1, StockMovementReason::Sold);

        $this->expectException(InsufficientStock::class);

        // A second buyer holding a stale copy of the variant, which still says one is left.
        $stale = (new ProductVariant)->newFromBuilder(['id' => $variant->id, 'sku' => $variant->sku, 'stock_on_hand' => 1]);
        $this->ledger->adjust($stale, -1, StockMovementReason::Sold);
    }

    public function test_the_database_itself_refuses_negative_stock(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->expectException(QueryException::class);

        $variant->forceFill(['stock_on_hand' => -1])->save();
    }

    public function test_a_movement_must_change_the_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->ledger->adjust(ProductVariant::factory()->create(), 0, StockMovementReason::Correction);
    }

    public function test_a_stock_count_records_the_difference_as_a_correction(): void
    {
        $variant = ProductVariant::factory()->withStock(10)->create();

        $movement = $this->ledger->setTo($variant, 7, 'Monthly count');

        $this->assertSame(-3, $movement->quantity);
        $this->assertSame(7, $movement->balance_after);
        $this->assertSame(StockMovementReason::Correction, $movement->reason);
        $this->assertSame(7, $variant->fresh()->stock_on_hand);
    }

    public function test_a_stock_count_that_matches_records_nothing(): void
    {
        $variant = ProductVariant::factory()->withStock(10)->create();

        $this->assertNull($this->ledger->setTo($variant, 10));
        $this->assertSame(0, StockMovement::count());
    }

    public function test_movements_cannot_be_edited_or_deleted(): void
    {
        $variant = ProductVariant::factory()->create();
        $movement = $this->ledger->adjust($variant, 4, StockMovementReason::Received);

        try {
            $movement->update(['quantity' => 40]);
            $this->fail('Editing a stock movement should have been refused.');
        } catch (LogicException) {
            $this->assertSame(4, $movement->fresh()->quantity);
        }

        $this->expectException(LogicException::class);

        $movement->delete();
    }
}
