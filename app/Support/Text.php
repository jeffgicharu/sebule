<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

class Text
{
    /**
     * A name for display, with hyphenated words such as "3-seater" kept on one
     * line so a heading never breaks in the middle of one.
     */
    public static function unbroken(string $name): HtmlString
    {
        return new HtmlString(preg_replace(
            '/(\S+-\S+)/u',
            '<span class="whitespace-nowrap">$1</span>',
            e($name),
        ));
    }
}
