<?php

namespace App\Console\Commands;

use App\Catalogue\SearchIndex;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalogue:index-search')]
#[Description('Write again the text every product is searched by')]
class IndexSearch extends Command
{
    public function handle(): int
    {
        $written = SearchIndex::rebuild();

        $this->info("Wrote the search text of {$written} ".str('product')->plural($written).'.');

        return self::SUCCESS;
    }
}
