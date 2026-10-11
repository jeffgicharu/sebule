<?php

namespace App\Http\Controllers\Shop;

use App\Enums\FooterGroup;
use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * One of the store's own pages. A page that is not published yet can be read
 * by staff who are signed in, so it can be checked before customers see it.
 */
class PageController extends Controller
{
    public function __invoke(Page $page): View
    {
        abort_unless($page->is_published || Auth::guard('web')->check(), 404);

        return view('shop.page', [
            'page' => $page,
            'others' => Page::query()->published()
                ->where('footer_group', $page->footer_group ?? FooterGroup::Help)
                ->whereKeyNot($page->getKey())
                ->orderBy('position')
                ->get(),
            'zones' => $page->slug === 'delivery'
                ? DeliveryZone::query()->active()->has('counties')->with('counties')->orderBy('position')->get()
                : collect(),
        ]);
    }
}
