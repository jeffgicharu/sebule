<?php

namespace Database\Seeders;

use App\Enums\FooterGroup;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * The store's own pages, as first written. A page that already exists is
 * left alone, so whatever staff have changed since is never overwritten.
 */
class PageSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2: string, 3: FooterGroup}> slug, title, summary, footer list */
    private const PAGES = [
        ['delivery', 'Delivery', 'How Sebule delivers across Kenya: what it costs for each county, how long it takes and what happens on the day.', FooterGroup::Help],
        ['returns', 'Returns', 'What to do if you change your mind about a Sebule order, or if a piece arrives damaged.', FooterGroup::Help],
        ['warranty', 'Our guarantee', 'What Sebule guarantees, for how long, and how to make a claim.', FooterGroup::Help],
        ['faqs', 'Questions and answers', 'Answers to what customers ask most about ordering, paying with M-Pesa, delivery and looking after the furniture.', FooterGroup::Help],
        ['contact', 'Contact us', 'How to reach Sebule about an order or a piece you are thinking of buying.', FooterGroup::Help],
        ['about', 'About Sebule', 'Sebule designs and builds furniture in Nairobi from solid mvule, mahogany and Meru oak, and delivers it across Kenya.', FooterGroup::About],
        ['showroom', 'Our showroom', 'Visit the Sebule showroom in Nairobi to see and sit on the furniture, take home samples or collect an order.', FooterGroup::About],
        ['terms', 'Terms of sale', 'The terms that apply when you buy from Sebule.', FooterGroup::Legal],
        ['privacy', 'Privacy', 'What Sebule knows about its customers, why, and the rights you have over it under the Data Protection Act, 2019.', FooterGroup::Legal],
    ];

    public function run(): void
    {
        foreach (self::PAGES as $position => [$slug, $title, $summary, $group]) {
            Page::query()->firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'summary' => $summary,
                'body' => trim(file_get_contents(__DIR__."/data/pages/{$slug}.md")),
                'footer_group' => $group,
                'position' => $position,
                'is_published' => true,
            ]);
        }
    }
}
