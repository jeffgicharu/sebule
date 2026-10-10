<?php

namespace Tests\Feature\Catalogue;

use App\Models\Product;
use App\Models\ProductOptionValue;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class ImportCataloguePhotosTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed([CategorySeeder::class, CatalogueSeeder::class]);

        $this->path = sys_get_temp_dir().'/sebule-photos-'.uniqid();
        File::makeDirectory($this->path);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    public function test_photos_go_to_the_colour_they_show_and_room_shots_to_the_product(): void
    {
        $this->photo('amani-sofa-3s_sand_studio-34.jpg');
        $this->photo('amani-sofa-3s_sand_room.jpg');
        $this->photo('amani-sofa-3s_sand_detail-arm.jpg');
        $this->photo('amani-sofa-3s_sand_studio-front.jpg');
        $this->photo('amani-sofa-3s_forest_studio-34.jpg');

        $this->artisan('catalogue:import-photos', ['path' => $this->path])
            ->expectsOutputToContain('5 photos added, 0 replaced, 0 already in place.')
            ->assertSuccessful();

        $sofa = Product::where('slug', 'amani-3-seater-sofa')->with('options.values')->first();
        $sand = $sofa->options->first()->values->firstWhere('label', 'Sand');
        $forest = $sofa->options->first()->values->firstWhere('label', 'Forest');

        $this->assertSame(
            ['amani-sofa-3s_sand_studio-34.jpg', 'amani-sofa-3s_sand_studio-front.jpg', 'amani-sofa-3s_sand_detail-arm.jpg'],
            $sand->getMedia(ProductOptionValue::IMAGES)->pluck('file_name')->all(),
        );
        $this->assertSame(['amani-sofa-3s_forest_studio-34.jpg'], $forest->getMedia(ProductOptionValue::IMAGES)->pluck('file_name')->all());
        $this->assertSame(['amani-sofa-3s_sand_room.jpg'], $sofa->getMedia(Product::GALLERY)->pluck('file_name')->all());
        $this->assertSame('public', Media::first()->disk);
    }

    public function test_a_product_without_options_keeps_its_photo_itself(): void
    {
        $this->photo('kilifi-planter-basket_default_studio-34.jpg');

        $this->artisan('catalogue:import-photos', ['path' => $this->path])->assertSuccessful();

        $basket = Product::where('slug', 'kilifi-planter-basket')->first();

        $this->assertSame(['kilifi-planter-basket_default_studio-34.jpg'], $basket->getMedia(Product::GALLERY)->pluck('file_name')->all());
    }

    public function test_rejected_takes_and_unknown_files_are_ignored(): void
    {
        $this->photo('amani-sofa-3s_sand_studio-34.jpg');
        $this->photo('amani-sofa-3s_sand_studio-34_v1.jpg');
        $this->photo('home-hero-1.jpg');

        $this->artisan('catalogue:import-photos', ['path' => $this->path])
            ->expectsOutputToContain('1 photos added')
            ->assertSuccessful();

        $this->assertSame(1, Media::count());
    }

    public function test_running_it_again_adds_nothing(): void
    {
        $this->photo('amani-sofa-3s_sand_studio-34.jpg');

        $this->artisan('catalogue:import-photos', ['path' => $this->path])->assertSuccessful();
        $this->artisan('catalogue:import-photos', ['path' => $this->path])
            ->expectsOutputToContain('0 photos added, 0 replaced, 1 already in place.')
            ->assertSuccessful();

        $this->assertSame(1, Media::count());
        $this->assertFileExists($this->path.'/amani-sofa-3s_sand_studio-34.jpg');
    }

    public function test_replace_swaps_the_file_and_keeps_its_place_in_the_gallery(): void
    {
        $this->photo('amani-sofa-3s_sand_studio-34.jpg');
        $this->photo('amani-sofa-3s_sand_studio-front.jpg');
        $this->artisan('catalogue:import-photos', ['path' => $this->path])->assertSuccessful();

        $before = Media::where('file_name', 'amani-sofa-3s_sand_studio-34.jpg')->first();

        $this->artisan('catalogue:import-photos', ['path' => $this->path, '--replace' => true])
            ->expectsOutputToContain('0 photos added, 2 replaced, 0 already in place.')
            ->assertSuccessful();

        $after = Media::where('file_name', 'amani-sofa-3s_sand_studio-34.jpg')->first();

        $this->assertSame(2, Media::count());
        $this->assertNotSame($before->id, $after->id);
        $this->assertSame($before->order_column, $after->order_column);
        $this->assertSame('amani-sofa-3s_sand_studio-34.jpg', Media::orderBy('order_column')->first()->file_name);
    }

    public function test_it_says_which_products_have_no_photo(): void
    {
        $this->photo('amani-sofa-3s_sand_studio-34.jpg');

        $this->artisan('catalogue:import-photos', ['path' => $this->path])
            ->expectsOutputToContain('79 products have no photo')
            ->assertSuccessful();
    }

    public function test_it_refuses_a_directory_that_does_not_exist(): void
    {
        $this->artisan('catalogue:import-photos', ['path' => $this->path.'/missing'])->assertFailed();
    }

    private function photo(string $name): void
    {
        $image = imagecreatetruecolor(64, 64);
        imagefill($image, 0, 0, imagecolorallocate($image, 239, 234, 226));
        imagejpeg($image, $this->path.'/'.$name, 80);
        imagedestroy($image);
    }
}
