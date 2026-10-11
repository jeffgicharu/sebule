<?php

namespace App\Models;

use App\Enums\FooterGroup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One of the store's own pages, written in Markdown by staff.
 */
#[Fillable(['slug', 'title', 'summary', 'body', 'footer_group', 'position', 'is_published'])]
class Page extends Model
{
    protected function casts(): array
    {
        return [
            'footer_group' => FooterGroup::class,
            'is_published' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @param Builder<Page> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * The body as HTML. Whatever HTML was typed into it is shown as text, so
     * a page can never carry a script.
     */
    public function html(): string
    {
        return Str::markdown($this->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    /**
     * The questions and answers of a page written as headings and paragraphs,
     * for search engines to read as such.
     *
     * @return list<array{question: string, answer: string}>
     */
    public function questions(): array
    {
        preg_match_all('/^###\s+(.+?\?)\s*\n+(.+?)(?=\n#{2,3}\s|\z)/ms', $this->body, $found, PREG_SET_ORDER);

        return array_map(fn (array $pair) => [
            'question' => trim($pair[1]),
            'answer' => trim(strip_tags(Str::markdown(trim($pair[2])))),
        ], $found);
    }
}
