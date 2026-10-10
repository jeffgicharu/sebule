<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductOptionValue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\MediaLibrary\HasMedia;

/**
 * Attaches photo files to the catalogue's products.
 *
 * Files are named <photo id>_<option value>_<shot>.jpg, where the option
 * value is "default" for a product with no options and the shot is one of
 * studio-34, studio-front, studio-side, studio-back, detail (or detail-arm
 * and the like) and room. A room shot belongs to the product whatever colour
 * is chosen; every other shot belongs to the colour or finish it shows.
 */
#[Signature('catalogue:import-photos {path : Directory holding the photo files} {--replace : Swap in the file when a photo of the same name is already attached}')]
#[Description('Attach product photos from a directory to the catalogue')]
class ImportCataloguePhotos extends Command
{
    /** Shots in the order they appear in a gallery. */
    private const SHOT_ORDER = ['studio-34', 'studio-front', 'studio-side', 'studio-back', 'detail', 'room'];

    public function handle(): int
    {
        $path = rtrim($this->argument('path'), '/');

        if (! File::isDirectory($path)) {
            $this->error("No directory at {$path}.");

            return self::FAILURE;
        }

        $catalogue = json_decode(
            File::get(database_path('seeders/data/catalogue.json')),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $added = $replaced = $skipped = 0;
        $withoutPhotos = [];

        foreach ($catalogue['products'] as $entry) {
            $product = Product::query()->where('slug', $entry['slug'])->with('options.values')->first();

            if ($product === null) {
                continue;
            }

            $photos = $this->photosFor($path, $entry['image_id']);

            if ($photos === []) {
                $withoutPhotos[] = $entry['name'];

                continue;
            }

            foreach ($photos as $photo) {
                [$model, $collection] = $this->ownerOf($product, $entry, $photo);

                $existing = $model->getMedia($collection)->firstWhere('file_name', $photo['name']);

                if ($existing !== null && ! $this->option('replace')) {
                    $skipped++;

                    continue;
                }

                $position = $existing?->order_column;
                $existing?->delete();

                $media = $model->addMedia($photo['file'])
                    ->preservingOriginal()
                    ->usingFileName($photo['name'])
                    ->usingName($product->name)
                    ->toMediaCollection($collection);

                // A replacement takes the place in the gallery of the photo it replaces.
                if ($position !== null) {
                    $media->update(['order_column' => $position]);
                }

                $model->unsetRelation('media');

                $existing === null ? $added++ : $replaced++;
            }
        }

        $this->info("{$added} photos added, {$replaced} replaced, {$skipped} already in place.");

        if ($withoutPhotos !== []) {
            $this->warn(count($withoutPhotos).' products have no photo: '.implode(', ', $withoutPhotos).'.');
        }

        return self::SUCCESS;
    }

    /**
     * The photo files for one product, in gallery order.
     *
     * @return array<int, array{file: string, name: string, variant: string, shot: string}>
     */
    private function photosFor(string $path, string $photoId): array
    {
        $photos = [];

        foreach (File::glob("{$path}/{$photoId}_*.{jpg,jpeg,png,webp}", GLOB_BRACE) as $file) {
            $name = basename($file);
            $pattern = '/^'.preg_quote($photoId, '/').'_([a-z0-9-]+)_(studio-34|studio-front|studio-side|studio-back|room|detail(?:-[a-z0-9-]+)?)\.[a-z]+$/';

            // Anything else, such as a rejected take, is not a catalogue photo.
            if (! preg_match($pattern, $name, $match)) {
                continue;
            }

            $photos[] = ['file' => $file, 'name' => $name, 'variant' => $match[1], 'shot' => $match[2]];
        }

        usort($photos, fn (array $a, array $b) => [$this->rank($a['shot']), $a['name']] <=> [$this->rank($b['shot']), $b['name']]);

        return $photos;
    }

    private function rank(string $shot): int
    {
        $position = array_search(str_starts_with($shot, 'detail') ? 'detail' : $shot, self::SHOT_ORDER, true);

        return $position === false ? count(self::SHOT_ORDER) : $position;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array{file: string, name: string, variant: string, shot: string}  $photo
     * @return array{0: HasMedia, 1: string}
     */
    private function ownerOf(Product $product, array $entry, array $photo): array
    {
        if ($photo['shot'] === 'room' || $entry['option'] === null) {
            return [$product, Product::GALLERY];
        }

        $label = collect($entry['option']['values'])->firstWhere('slug', $photo['variant'])['label'] ?? null;
        $value = $product->options->flatMap->values->firstWhere('label', $label);

        return $value instanceof ProductOptionValue
            ? [$value, ProductOptionValue::IMAGES]
            : [$product, Product::GALLERY];
    }
}
