<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Enums\FooterGroup;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PageForm
{
    /** Addresses the shop itself uses, which a page cannot take. */
    private const TAKEN = ['admin', 'account', 'cart', 'checkout', 'orders', 'payments', 'search', 'c', 'p', 'collections', 'up', 'storage', 'build', 'livewire', 'images'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('slug')
                            ->label('Web address')
                            ->prefix(parse_url(config('app.url'), PHP_URL_HOST).'/')
                            ->helperText('Small letters, numbers and dashes. Changing it breaks links to the page from elsewhere.')
                            ->required()
                            ->maxLength(80)
                            ->regex('/^[a-z0-9]+(-[a-z0-9]+)*$/')
                            ->notIn(self::TAKEN)
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'regex' => 'Use small letters, numbers and dashes only, such as "care-guide".',
                                'not_in' => 'The shop already uses that address for something else.',
                            ]),
                        Textarea::make('summary')
                            ->helperText('One or two sentences. Search engines show this under the page\'s title.')
                            ->rows(2)
                            ->maxLength(300)
                            ->columnSpanFull(),
                        MarkdownEditor::make('body')
                            ->label('The page')
                            ->helperText('Start a line with ## for a heading. A heading that starts with ### and ends in a question mark is read by search engines as a question, and the paragraph under it as its answer.')
                            ->toolbarButtons([['bold', 'italic', 'link'], ['heading'], ['bulletList', 'orderedList', 'table'], ['undo', 'redo']])
                            ->required()
                            ->columnSpanFull(),
                        Select::make('footer_group')
                            ->label('Linked from the foot of every page, under')
                            ->options(FooterGroup::class)
                            ->placeholder('Not linked from there'),
                        Toggle::make('is_published')
                            ->label('Published')
                            ->helperText('Until this is on, only staff who are signed in can open the page. Save, then use Preview to read it as it will look.')
                            ->inline(false),
                    ]),
            ]);
    }
}
