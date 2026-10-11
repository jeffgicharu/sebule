<?php

namespace Tests\Feature\Shop;

use App\Enums\FooterGroup;
use App\Enums\StaffRole;
use App\Models\DeliveryZone;
use App\Models\Page;
use App\Models\User;
use App\Support\StoreSettings;
use App\Support\WhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PlacesOrders;
use Tests\TestCase;

/**
 * The store's own pages, the links to them, and the ways to reach the store.
 */
class StorePagesTest extends TestCase
{
    use PlacesOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_store_ships_with_the_pages_a_customer_looks_for(): void
    {
        foreach ([
            'delivery' => 'We deliver to every county in Kenya.',
            'returns' => 'within 7 days of delivery',
            'warranty' => 'Solid wood tables, beds, storage and frames',
            'faqs' => 'Do I need an account to order?',
            'contact' => 'We reply within one working day.',
            'about' => 'the Swahili word for the sitting room',
            'showroom' => 'Collecting an order',
            'terms' => 'These terms are governed by the laws of Kenya.',
            'privacy' => 'Office of the Data Protection Commissioner',
        ] as $slug => $says) {
            $page = Page::where('slug', $slug)->firstOrFail();

            $this->get('/'.$slug)->assertOk()
                ->assertSee('<title>'.e($page->title).' | Sebule</title>', escape: false)
                ->assertSee('<meta name="description" content="'.e($page->summary).'">', escape: false)
                ->assertSee('<link rel="canonical" href="'.url('/'.$slug).'">', escape: false)
                ->assertSeeText($says);
        }
    }

    public function test_every_page_links_to_them_from_its_foot(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSeeInOrder(['aria-label="Help"', url('/delivery'), url('/returns'), url('/warranty'), url('/faqs'), url('/contact')], escape: false)
            ->assertSee(url('/about'))
            ->assertSee(url('/showroom'))
            ->assertSeeInOrder([url('/terms'), url('/privacy')], escape: false);
    }

    public function test_a_page_that_is_not_published_is_seen_by_staff_only(): void
    {
        $draft = Page::create(['slug' => 'care-guide', 'title' => 'Care guide', 'body' => 'Dust it weekly.', 'footer_group' => FooterGroup::Help, 'is_published' => false]);

        $this->get('/care-guide')->assertNotFound();
        $this->get(route('home'))->assertDontSee(url('/care-guide'));
        $this->get(route('sitemap'))->assertDontSee('care-guide');

        $this->actingAs(User::factory()->create(['role' => StaffRole::Manager]));

        $this->get('/care-guide')->assertOk()
            ->assertSeeText('This page is not published. Only staff who are signed in can see it.')
            ->assertSee('<meta name="robots" content="noindex">', escape: false)
            ->assertSeeText('Dust it weekly.');

        $draft->update(['is_published' => true]);
        auth()->logout();

        $this->get('/care-guide')->assertOk()->assertDontSeeText('This page is not published.');
        $this->get(route('home'))->assertSee(url('/care-guide'));
        $this->get(route('sitemap'))->assertSee(url('/care-guide'));
    }

    public function test_an_address_that_is_no_page_is_not_found_and_the_back_office_keeps_its_own(): void
    {
        $this->get('/no-such-page')->assertNotFound();
        $this->get('/Delivery')->assertNotFound();
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_a_page_shows_markdown_as_a_page_and_typed_html_as_text(): void
    {
        Page::create([
            'slug' => 'care-guide', 'title' => 'Care guide', 'is_published' => true,
            'body' => "## Wood\n\nUse a **dry** cloth. [Ask us](/contact).\n\n<script>alert(1)</script>\n\n[bad](javascript:alert(1))",
        ]);

        $this->get('/care-guide')->assertOk()
            ->assertSee('<h2>Wood</h2>', escape: false)
            ->assertSee('<strong>dry</strong>', escape: false)
            ->assertSee('<a href="/contact">Ask us</a>', escape: false)
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertDontSee('javascript:alert', escape: false);
    }

    public function test_the_delivery_page_lists_what_each_zone_costs_today(): void
    {
        $this->get('/delivery')->assertOk()
            ->assertSeeTextInOrder(['What delivery costs', 'Nairobi', 'KSh 1,500', 'in 1 to 3 working days', 'Around Nairobi', 'KSh 2,500', 'Kajiado, Kiambu, Machakos']);

        DeliveryZone::where('name', 'Nairobi')->update(['fee' => 180000]);
        DeliveryZone::where('name', 'Coast')->update(['is_active' => false]);

        $this->get('/delivery')->assertOk()->assertSeeText('KSh 1,800')->assertDontSeeText('Mombasa');
    }

    public function test_the_questions_page_tells_search_engines_its_questions_and_answers(): void
    {
        $faqs = Page::where('slug', 'faqs')->firstOrFail();
        $questions = $faqs->questions();

        $this->assertCount(11, $questions);
        $this->assertSame('Do I need an account to order?', $questions[0]['question']);
        $this->assertStringStartsWith('No. You can check out with a name and a phone number.', $questions[0]['answer']);
        $this->assertStringNotContainsString('<', $questions[3]['answer']);

        $this->get('/faqs')->assertOk()
            ->assertSee('"@type":"FAQPage"', escape: false)
            ->assertSee('"name":"How do I pay?"', escape: false);

        $this->get('/delivery')->assertDontSee('FAQPage');
    }

    public function test_the_contact_page_shows_each_way_to_reach_the_store_that_has_been_set(): void
    {
        $this->get('/contact')->assertOk()
            ->assertSeeText('Reach us')
            ->assertSeeText('Ngong Road, Nairobi')
            ->assertSeeText('Monday to Saturday, 9 am to 6 pm')
            ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=Ngong%20Road%2C%20Nairobi', escape: false)
            ->assertDontSeeText('Chat with us on WhatsApp')
            ->assertDontSee('tel:')
            ->assertDontSee('mailto:');

        StoreSettings::save(['store.phone' => '254712000111', 'store.whatsapp' => '254733000222', 'store.email.reply_to' => 'hello@sebule.test']);

        $this->get('/contact')->assertOk()
            ->assertSee('href="tel:+254712000111"', escape: false)
            ->assertSeeText('0712 000 111')
            ->assertSee('href="https://wa.me/254733000222?text=Hello%20Sebule%2C%20I%20have%20a%20question."', escape: false)
            ->assertSee('href="mailto:hello@sebule.test"', escape: false);

        $this->get('/showroom')->assertOk()->assertSeeText('Chat with us on WhatsApp');
        $this->get('/delivery')->assertOk()->assertDontSeeText('Chat with us on WhatsApp');
    }

    public function test_a_whatsapp_chat_opens_already_saying_which_piece_or_order_it_is_about(): void
    {
        $piece = $this->piece('Amani sofa');
        $order = $this->paidOrder(piece: $piece);
        $product = route('shop.product', $piece->product);

        $this->assertNull(WhatsApp::link('Hello'));
        $this->get($product)->assertOk()->assertDontSeeText('Ask about this piece on WhatsApp');
        $this->get(route('orders.show', $order))->assertOk()
            ->assertDontSeeText('on WhatsApp')
            ->assertSeeText('Ask us about this order')
            ->assertSee(url('/contact'));

        StoreSettings::save(['store.whatsapp' => '254733000222']);

        $this->get($product)->assertOk()
            ->assertSeeText('Ask about this piece on WhatsApp')
            ->assertSee('https://wa.me/254733000222?text='.rawurlencode('Hello Sebule, I have a question about the Amani sofa: '.$product), escape: false);

        $this->get(route('orders.show', $order))->assertOk()
            ->assertSee('https://wa.me/254733000222?text='.rawurlencode('Hello Sebule, I am asking about order '.$order->number.'.'), escape: false);
    }

    public function test_the_sitemap_lists_the_published_pages(): void
    {
        $this->get(route('sitemap'))->assertOk()
            ->assertSee('<loc>'.url('/delivery').'</loc>', escape: false)
            ->assertSee('<loc>'.url('/privacy').'</loc>', escape: false);
    }

    public function test_the_shop_says_how_it_is_actually_paid_for(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSeeText('Pay with M-Pesa')
            ->assertDontSeeText('or card');
    }
}
