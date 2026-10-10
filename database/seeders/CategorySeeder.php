<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The shop's departments. Safe to run again: categories are matched by slug
 * and anything staff have changed since is left as it is.
 */
class CategorySeeder extends Seeder
{
    /** @var array<string, array<int, string>> */
    private const TREE = [
        'Living room' => ['Sofas', 'Armchairs', 'Coffee tables', 'TV units', 'Shelving'],
        'Bedroom' => ['Beds', 'Bedside tables', 'Wardrobes', 'Dressers'],
        'Dining' => ['Dining tables', 'Dining chairs', 'Sideboards'],
        'Office' => ['Desks', 'Office chairs', 'Bookcases'],
        'Outdoor' => ['Outdoor seating', 'Outdoor tables'],
        'Lighting' => ['Table lamps', 'Floor lamps', 'Pendant lights'],
        'Decor' => ['Rugs', 'Cushions', 'Mirrors', 'Baskets'],
    ];

    public function run(): void
    {
        $position = 0;

        foreach (self::TREE as $parentName => $children) {
            $parent = $this->category($parentName, null, ++$position);

            foreach ($children as $index => $childName) {
                $this->category($childName, $parent, $index + 1);
            }
        }
    }

    private function category(string $name, ?Category $parent, int $position): Category
    {
        return Category::firstOrCreate(
            ['slug' => str($name)->slug()->toString()],
            ['name' => $name, 'parent_id' => $parent?->getKey(), 'position' => $position, 'is_active' => true],
        );
    }
}
