<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-####-??')),
            'price' => $this->faker->numberBetween(50, 2000) * 100_00,
            'is_default' => true,
        ];
    }

    /** Stock is not mass assignable, so it is set after the variant exists. */
    public function withStock(int $quantity): static
    {
        return $this->afterCreating(function (ProductVariant $variant) use ($quantity): void {
            $variant->forceFill(['stock_on_hand' => $quantity])->save();
        });
    }

    public function madeToOrder(int $weeks = 3): static
    {
        return $this->state(['made_to_order' => true, 'lead_time_weeks' => $weeks]);
    }
}
