<?php

namespace App\Inventory;

use App\Enums\StockMovementReason;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only place stock changes. Each change locks the variant's row, so two
 * requests can never both take the last item, and each is written down with
 * who made it, why, and the balance it left.
 */
class StockLedger
{
    /**
     * Add stock (positive quantity) or remove it (negative quantity).
     *
     * @throws InsufficientStock when removing more than is on hand
     */
    public function adjust(
        ProductVariant $variant,
        int $quantity,
        StockMovementReason $reason,
        ?string $note = null,
        ?User $by = null,
        ?Model $source = null,
    ): StockMovement {
        if ($quantity === 0) {
            throw new InvalidArgumentException('A stock movement must change the quantity.');
        }

        return DB::transaction(function () use ($variant, $quantity, $reason, $note, $by, $source): StockMovement {
            $locked = ProductVariant::query()->lockForUpdate()->findOrFail($variant->getKey());

            $balance = $locked->stock_on_hand + $quantity;

            if ($balance < 0) {
                throw new InsufficientStock($locked, -$quantity, $locked->stock_on_hand);
            }

            $locked->forceFill(['stock_on_hand' => $balance])->save();
            $variant->setAttribute('stock_on_hand', $balance)->syncOriginalAttribute('stock_on_hand');

            return StockMovement::create([
                'product_variant_id' => $locked->getKey(),
                'user_id' => $by?->getKey(),
                'quantity' => $quantity,
                'balance_after' => $balance,
                'reason' => $reason,
                'note' => $note,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
            ]);
        });
    }

    /**
     * Set stock to a counted figure, recording the difference as a correction.
     * Returns null when the count matches what is already recorded.
     */
    public function setTo(ProductVariant $variant, int $counted, ?string $note = null, ?User $by = null): ?StockMovement
    {
        if ($counted < 0) {
            throw new InvalidArgumentException('Stock cannot be counted below zero.');
        }

        return DB::transaction(function () use ($variant, $counted, $note, $by): ?StockMovement {
            $current = ProductVariant::query()->lockForUpdate()->findOrFail($variant->getKey())->stock_on_hand;

            if ($counted === $current) {
                return null;
            }

            return $this->adjust($variant, $counted - $current, StockMovementReason::Correction, $note, $by);
        });
    }
}
