<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * The seeder never overwrites a product that already exists. When the wording
 * in the catalogue file is corrected, this applies it to the products named.
 */
#[Signature('catalogue:refresh-copy {slugs* : Web address names of the products to refresh}')]
#[Description('Replace the summary, description, materials and care text of named products with the catalogue file\'s wording')]
class RefreshCatalogueCopy extends Command
{
    public function handle(): int
    {
        $entries = collect(json_decode(
            File::get(database_path('seeders/data/catalogue.json')),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        )['products'])->keyBy('slug');

        $failed = false;

        foreach ($this->argument('slugs') as $slug) {
            $entry = $entries->get($slug);
            $product = Product::query()->where('slug', $slug)->first();

            if ($entry === null || $product === null) {
                $this->error("{$slug}: not found in ".($entry === null ? 'the catalogue file' : 'the shop').'.');
                $failed = true;

                continue;
            }

            $product->update([
                'summary' => $entry['summary'],
                'description' => '<p>'.e($entry['description']).'</p>',
                'materials' => $entry['materials'],
                'care' => $entry['care'],
            ]);

            $this->line("{$slug}: refreshed.");
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
