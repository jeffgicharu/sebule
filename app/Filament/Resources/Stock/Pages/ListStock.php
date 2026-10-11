<?php

namespace App\Filament\Resources\Stock\Pages;

use App\Filament\Resources\Stock\StockResource;
use Filament\Resources\Pages\ListRecords;

class ListStock extends ListRecords
{
    protected static string $resource = StockResource::class;

    protected static ?string $title = 'Stock';
}
