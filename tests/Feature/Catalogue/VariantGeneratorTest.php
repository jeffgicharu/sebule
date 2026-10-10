<?php

namespace Tests\Feature\Catalogue;

use App\Catalogue\VariantGenerator;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariantGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private VariantGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new VariantGenerator;
    }

    public function test_a_product_without_options_gets_one_variant(): void
    {
        $product = Product::factory()->create(['name' => 'Kito table lamp']);

        $created = $this->generator->generate($product, price: 850_000);

        $this->assertCount(1, $created);
        $this->assertSame('KITO-TABLE-LAMP', $created->first()->sku);
        $this->assertSame(850_000, $created->first()->price);
        $this->assertTrue($created->first()->is_default);
    }

    public function test_it_creates_a_variant_for_every_combination(): void
    {
        $product = $this->sofaWithOptions(['Sand', 'Forest', 'Clay'], ['2-seater', '3-seater']);

        $created = $this->generator->generate($product, price: 6_500_000);

        $this->assertCount(6, $created);
        $this->assertSame(1, $product->variants()->where('is_default', true)->count());

        $labels = $product->variants()->with('optionValues.option')->get()
            ->map(fn (ProductVariant $variant) => $variant->label())
            ->all();

        $this->assertContains('Sand / 2-seater', $labels);
        $this->assertContains('Clay / 3-seater', $labels);
        $this->assertSame('AMANI-SOFA-SAND-2-SEATER', $product->variants()->first()->sku);
    }

    public function test_running_it_again_adds_only_the_missing_variants(): void
    {
        $product = $this->sofaWithOptions(['Sand', 'Forest'], ['3-seater']);
        $this->generator->generate($product, price: 6_500_000);

        $product->variants()->first()->update(['price' => 7_000_000]);
        $product->options()->where('name', 'Colour')->first()->values()->create(['label' => 'Clay', 'position' => 3]);

        $created = $this->generator->generate($product, price: 6_500_000);

        $this->assertCount(1, $created);
        $this->assertSame('AMANI-SOFA-CLAY-3-SEATER', $created->first()->sku);
        $this->assertFalse($created->first()->is_default);
        $this->assertSame(3, $product->variants()->count());
        $this->assertSame(7_000_000, $product->variants()->first()->price);
    }

    /**
     * @param  array<int, string>  $colours
     * @param  array<int, string>  $sizes
     */
    private function sofaWithOptions(array $colours, array $sizes): Product
    {
        $product = Product::factory()->create(['name' => 'Amani sofa']);

        foreach (['Colour' => $colours, 'Size' => $sizes] as $name => $labels) {
            $option = $product->options()->create(['name' => $name, 'position' => $name === 'Colour' ? 1 : 2]);

            foreach ($labels as $index => $label) {
                $option->values()->create(['label' => $label, 'position' => $index + 1]);
            }
        }

        return $product;
    }
}
