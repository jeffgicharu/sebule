<?php

namespace App\Demo;

use App\Enums\DeliveryMethod;
use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\StaffRole;
use App\Enums\StockMovementReason;
use App\Messages\TellCustomer;
use App\Models\County;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Orders\CancelOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * The orders, customers and stock a demonstration store opens each day with:
 * four months of trading that never happened, shaped like trading that did.
 *
 * It replaces what was there. The catalogue, the pages and the store's
 * settings are not touched, and neither are staff accounts that are not
 * demonstration accounts. The same set is produced every time, with its dates
 * counted back from today.
 */
class DemoData
{
    /** How far back the history goes, in days. */
    private const DAYS = 120;

    /** Orders younger than this may still be on their way; older ones are settled. */
    private const OPEN_DAYS = 12;

    /** The demonstration staff: who a visitor is signed in as for each role they can try. */
    public const STAFF = [
        'manager' => ['Njeri Mwangi', 'njeri.mwangi@example.com', StaffRole::Manager],
        'fulfilment' => ['Otieno Odhiambo', 'otieno.odhiambo@example.com', StaffRole::Fulfilment],
        'support' => ['Brian Kiprop', 'brian.kiprop@example.com', StaffRole::Support],
    ];

    public const CUSTOMER = ['Wanjiru Kamau', 'wanjiru.kamau@example.com', self::PHONE_BLOCK.'0100'];

    /**
     * Every phone number in the demonstration data begins with this. The
     * numbers are made up but have the shape of real ones, so one of them may
     * be somebody's line: nothing is ever sent to a number in this block.
     */
    public const PHONE_BLOCK = '25470000';

    private const FIRST_NAMES = ['Achieng', 'Kamau', 'Wambui', 'Otieno', 'Njoki', 'Mutua', 'Akinyi', 'Kiprono', 'Nyambura', 'Omondi', 'Wanjiku', 'Kibet', 'Atieno', 'Mwangi', 'Chebet', 'Barasa', 'Naliaka', 'Kariuki', 'Moraa', 'Juma', 'Zawadi', 'Githinji', 'Auma', 'Koech', 'Mumbi', 'Wafula', 'Nduta', 'Hassan', 'Faith', 'Dennis', 'Mercy', 'Kevin', 'Joyce', 'Peter', 'Esther', 'Samuel'];

    private const LAST_NAMES = ['Otieno', 'Njoroge', 'Wanjiru', 'Odhiambo', 'Mwangi', 'Kiplagat', 'Achieng', 'Mutiso', 'Kamau', 'Onyango', 'Nyaga', 'Cheruiyot', 'Wekesa', 'Karanja', 'Ochieng', 'Muthoni', 'Kimani', 'Ali', 'Maina', 'Rotich', 'Gitau', 'Owino', 'Ndungu', 'Jepkosgei'];

    /** @var array<string, array{0: int, 1: list<array{0: string, 1: string}>}> county => [share of orders, [town, estate] pairs] */
    private const PLACES = [
        'Nairobi' => [44, [['Kilimani', 'Riara Court'], ['Westlands', 'Brookside Gardens'], ['Karen', 'Marula Lane'], ['Lavington', 'Hatheru Road'], ['South B', 'Mariakani Estate'], ['Kileleshwa', 'Siaya Road'], ['Roysambu', 'Mirema Drive'], ['Lang\'ata', 'Phenom Estate']]],
        'Kiambu' => [15, [['Ruaka', 'Maple Court'], ['Thika', 'Section 9'], ['Kikuyu', 'Thogoto Road'], ['Juja', 'Gachororo Road']]],
        'Machakos' => [5, [['Athi River', 'Greenpark Estate'], ['Syokimau', 'Katani Road']]],
        'Kajiado' => [5, [['Kitengela', 'Milimani Estate'], ['Ongata Rongai', 'Tumaini Estate']]],
        'Nakuru' => [7, [['Nakuru', 'Milimani'], ['Naivasha', 'Kenyatta Avenue']]],
        'Mombasa' => [6, [['Nyali', 'Links Road'], ['Bamburi', 'Kiembeni Estate']]],
        'Kisumu' => [5, [['Milimani', 'Got Huma Road'], ['Kondele', 'Kibos Road']]],
        'Uasin Gishu' => [4, [['Eldoret', 'Elgon View']]],
        'Nyeri' => [3, [['Nyeri', 'Ring Road']]],
        'Meru' => [2, [['Meru', 'Milimani']]],
        'Kilifi' => [2, [['Kilifi', 'Mnarani']]],
        'Kakamega' => [2, [['Kakamega', 'Milimani Estate']]],
    ];

    private const CANCEL_REASONS = [
        'The customer asked us to cancel it.',
        'Placed twice by mistake.',
        'We could not reach the customer to arrange delivery.',
    ];

    private Randomizer $random;

    /** @var Collection<int, ProductVariant> */
    private Collection $variants;

    /** @var array<string, County> */
    private array $counties;

    /** @var array<int, int> stock still free to sell, by variant id */
    private array $stock = [];

    private User $fulfiller;

    private int $serial = 0;

    public function __construct(private readonly TellCustomer $tell) {}

    /** Whether a number belongs to the demonstration data on a store that is showing it. */
    public static function invented(?string $phone): bool
    {
        return config('store.demo') && $phone !== null && str_starts_with($phone, self::PHONE_BLOCK);
    }

    /**
     * Replace the orders, customers and stock with the demonstration set.
     *
     * @return array{orders: int, customers: int, messages: int}
     */
    public function reset(): array
    {
        // The same numbers every time, so the store a visitor finds is the store that was checked.
        $this->random = new Randomizer(new Mt19937(20261011));

        return DB::transaction(function (): array {
            $this->wipe();
            $this->restoreStock();
            $this->accounts();
            $this->history();

            return ['orders' => Order::count(), 'customers' => DB::table('buyers')->count(), 'messages' => Message::count()];
        });
    }

    private function wipe(): void
    {
        foreach (['messages', 'payments', 'order_events', 'order_items', 'orders', 'cart_items', 'carts', 'customer_addresses', 'customer_password_resets', 'customers', 'stock_movements'] as $table) {
            DB::table($table)->delete();
        }

        User::query()->where('is_demo', true)->delete();
    }

    /** Every variant goes back to the stock the catalogue opens with, counted a fortnight ago. */
    private function restoreStock(): void
    {
        $catalogue = json_decode(file_get_contents(database_path('seeders/data/catalogue.json')), associative: true, flags: JSON_THROW_ON_ERROR);
        $counted = now()->subDays(self::OPEN_DAYS + 2)->setTime(8, 30);

        foreach ($catalogue['products'] as $entry) {
            $lines = $entry['option'] === null
                ? [$entry['slug'] => $entry['stock']]
                : collect($entry['option']['values'])->mapWithKeys(fn (array $value) => [$entry['slug'].'-'.$value['slug'] => $value['stock']])->all();

            foreach ($lines as $sku => $stock) {
                $variant = ProductVariant::query()->where('sku', Str::upper($sku))->first();

                if ($variant === null) {
                    continue;
                }

                $variant->newQuery()->whereKey($variant->getKey())->toBase()->update(['stock_on_hand' => $stock]);

                if ($stock > 0) {
                    StockMovement::query()->forceCreate([
                        'product_variant_id' => $variant->getKey(), 'quantity' => $stock, 'balance_after' => $stock,
                        'reason' => StockMovementReason::Correction, 'note' => 'Stock count', 'created_at' => $counted,
                    ]);
                }
            }
        }
    }

    private function accounts(): void
    {
        foreach (self::STAFF as [$name, $email, $role]) {
            $staff = User::query()->forceCreate([
                'name' => $name, 'email' => $email, 'role' => $role, 'is_active' => true, 'is_demo' => true,
                // Nobody signs in with this: demonstration accounts are entered through their own address.
                'password' => Str::password(40),
            ]);

            if ($role === StaffRole::Fulfilment) {
                $this->fulfiller = $staff;
            }
        }
    }

    private function history(): void
    {
        $this->variants = ProductVariant::query()->where('is_active', true)->with('product', 'optionValues.option')->orderBy('id')->get();
        $this->stock = $this->variants->pluck('stock_on_hand', 'id')->all();
        $this->counties = County::query()->deliveredTo()->with('zone')->get()->keyBy('name')->all();

        if ($this->variants->isEmpty() || $this->counties === []) {
            return;
        }

        [$name, $email, $phone] = self::CUSTOMER;
        $account = Customer::query()->forceCreate([
            'name' => $name, 'email' => $email, 'phone' => $phone, 'password' => Str::password(40), 'marketing_opt_in' => true, 'is_demo' => true,
        ]);
        $home = ['county' => 'Nairobi', 'town' => 'Kilimani', 'estate' => 'Riara Court', 'house' => 'A2', 'directions' => 'Green gate opposite the pharmacy'];
        $account->addresses()->create(['county_id' => $this->counties['Nairobi']->id, 'is_default' => true] + collect($home)->except('county')->all());
        if (isset($this->counties['Kiambu'])) {
            $account->addresses()->create(['county_id' => $this->counties['Kiambu']->id, 'town' => 'Ruaka', 'estate' => 'Maple Court', 'house' => 'B4', 'is_default' => false]);
        }
        $regular = ['name' => $name, 'phone' => $phone, 'email' => $email, 'marketing' => true, 'account' => $account->id] + $home;

        $people = $this->people();
        $recent = [];

        for ($age = self::DAYS - 1; $age >= 0; $age--) {
            $day = now()->subDays($age);
            // A store finding its feet: about one order a day at the start and three by now, with more at the weekend.
            $busy = 1 + 2 * (self::DAYS - $age) / self::DAYS + ($day->isWeekend() ? 0.8 : 0);
            $orders = (int) floor($busy) + ($this->chance((int) round(fmod($busy, 1) * 100)) ? 1 : 0);

            for ($n = 0; $n < $orders; $n++) {
                // The account holder orders now and then; so do a few who came back; the rest are new faces.
                $person = match (true) {
                    in_array($age, [96, 61, 33, 9, 2], true) && $n === 0 => $regular,
                    default => $people[$this->chance(30) ? $this->random->getInt(0, 11) : $this->random->getInt(0, count($people) - 1)],
                };

                // Yesterday somebody paid and then cancelled, so there is a refund waiting for the owner to send.
                $order = $this->order($person, $age, $day, refund: $age === 1 && $n === 0);

                if ($order !== null && $age <= 4) {
                    $recent[] = $order;
                }
            }
        }

        // Two orders placed in the last few minutes and not yet paid for, their pieces held.
        foreach ([8, 3] as $minutes) {
            $this->order($people[$this->random->getInt(12, count($people) - 1)], 0, now(), awaiting: $minutes);
        }

        $this->messagesFor($recent);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function people(): array
    {
        $people = [];
        $weights = collect(self::PLACES)->filter(fn (array $place, string $county) => isset($this->counties[$county]))->map(fn (array $place) => $place[0]);

        foreach (range(1, 150) as $number) {
            $first = self::FIRST_NAMES[$this->random->getInt(0, count(self::FIRST_NAMES) - 1)];
            $last = self::LAST_NAMES[$this->random->getInt(0, count(self::LAST_NAMES) - 1)];
            $county = $this->weighted($weights->all());
            [$town, $estate] = $this->pick(self::PLACES[$county][1]);

            $people[] = [
                'name' => $first.' '.$last,
                // Numbers in a block chosen to look like nobody's, in the shape real ones have.
                'phone' => self::PHONE_BLOCK.str_pad((string) (1000 + $number), 4, '0', STR_PAD_LEFT),
                'email' => $this->chance(70) ? Str::lower($first.'.'.$last).$number.'@example.com' : null,
                'marketing' => $this->chance(35),
                'county' => $county, 'town' => $town, 'estate' => $estate,
                'house' => $this->chance(60) ? $this->pick(['A', 'B', 'C', 'D']).$this->random->getInt(1, 24) : null,
                'directions' => $this->chance(25) ? $this->pick(['Black gate after the supermarket', 'Call on arrival, the guard will direct you', 'Second building on the left, ground floor', 'Opposite the primary school']) : null,
                'account' => null,
            ];
        }

        return $people;
    }

    /**
     * One order with its lines, payments and history, as it would stand today.
     *
     * @param  array<string, mixed>  $person
     * @param  int|null  $awaiting  minutes ago it was placed, for an order still waiting to be paid for
     * @param  bool  $refund  whether it was paid for and then cancelled, leaving money to send back
     */
    private function order(array $person, int $age, Carbon $day, ?int $awaiting = null, bool $refund = false): ?Order
    {
        $open = $age < self::OPEN_DAYS;
        $collect = $this->chance(14);
        $county = $collect ? null : ($this->counties[$person['county']] ?? null);

        if (! $collect && $county === null) {
            return null;
        }

        $placed = $awaiting !== null
            ? now()->subMinutes($awaiting)
            : ($age === 0
                ? now()->subMinutes($this->random->getInt(25, 240))
                : $day->copy()->setTime($this->random->getInt(7, 21), $this->random->getInt(0, 59), $this->random->getInt(0, 59)));

        $lines = $this->lines($open);

        if ($lines === []) {
            return null;
        }

        $made = collect($lines)->contains(fn (array $line) => $line['variant']->made_to_order);
        $weeks = (int) collect($lines)->max(fn (array $line) => $line['variant']->made_to_order ? $line['variant']->lead_time_weeks : 0);
        $status = match (true) {
            $awaiting !== null => OrderStatus::PendingPayment,
            $refund => OrderStatus::Cancelled,
            default => $this->statusFor($age, $made, $weeks, $collect),
        };
        $takesStock = in_array($status, [OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::InProduction, OrderStatus::Ready, OrderStatus::OutForDelivery], true);

        $subtotal = (int) collect($lines)->sum(fn (array $line) => $line['variant']->price * $line['quantity']);
        $fee = $collect ? 0 : $county->zone->fee;
        $total = $subtotal + $fee;
        $rate = (int) config('store.vat_rate');
        $wasPaid = $status->isPaid() || $refund || ($status === OrderStatus::Cancelled && $this->chance(40));
        // Now and then the first prompt goes unanswered and the second is paid; an order never paid for had one that failed, or none.
        $failedAt = $awaiting === null && $this->chance($wasPaid ? 12 : 55) ? $placed->copy()->addSeconds($this->random->getInt(25, 70)) : null;
        $paidAt = $wasPaid ? ($failedAt ?? $placed)->copy()->addSeconds($this->random->getInt(40, 420)) : null;

        $order = Order::query()->forceCreate([
            'number' => 'SB-'.(100000 + (++$this->serial) * 37 + $this->random->getInt(0, 30)),
            'token' => Str::random(40), 'checkout_key' => (string) Str::uuid(), 'customer_id' => $person['account'],
            'status' => $status, 'customer_name' => $person['name'], 'phone' => $person['phone'], 'email' => $person['email'],
            'marketing_opt_in' => $person['marketing'],
            'delivery_method' => $collect ? DeliveryMethod::Collection : DeliveryMethod::Delivery,
            'delivery_zone_id' => $county?->zone->id, 'zone_name' => $county?->zone->name, 'county' => $county?->name,
            'town' => $collect ? null : $person['town'], 'estate' => $collect ? null : $person['estate'],
            'house' => $collect ? null : $person['house'], 'directions' => $collect ? null : $person['directions'],
            'delivery_days_min' => $county?->zone->days_min, 'delivery_days_max' => $county?->zone->days_max,
            'subtotal' => $subtotal, 'delivery_fee' => $fee, 'total' => $total, 'vat_rate' => $rate,
            'vat_amount' => (int) round($total * $rate / (10000 + $rate)),
            'held_until' => $status === OrderStatus::PendingPayment ? $placed->copy()->addMinutes((int) config('store.hold_minutes')) : null,
            'paid_at' => $paidAt, 'closed_at' => null, 'created_at' => $placed, 'updated_at' => $placed,
        ]);

        foreach ($lines as $line) {
            /** @var ProductVariant $variant */
            $variant = $line['variant'];
            $taken = $takesStock && ! $variant->made_to_order;

            $order->items()->forceCreate([
                'order_id' => $order->id, 'product_variant_id' => $variant->id, 'product_name' => $variant->product->name,
                'variant_label' => $variant->optionValues->isNotEmpty() ? $variant->label() : null, 'sku' => $variant->sku,
                'unit_price' => $variant->price, 'quantity' => $line['quantity'], 'line_total' => $variant->price * $line['quantity'],
                'made_to_order' => $variant->made_to_order, 'lead_time_weeks' => $variant->lead_time_weeks, 'stock_taken' => $taken,
                'created_at' => $placed, 'updated_at' => $placed,
            ]);

            if ($taken) {
                $this->stock[$variant->id] -= $line['quantity'];
                $variant->newQuery()->whereKey($variant->id)->toBase()->decrement('stock_on_hand', $line['quantity']);
                StockMovement::query()->forceCreate([
                    'product_variant_id' => $variant->id, 'quantity' => -$line['quantity'], 'balance_after' => $this->stock[$variant->id],
                    'reason' => StockMovementReason::Sold, 'note' => 'Order '.$order->number,
                    'source_type' => $order->getMorphClass(), 'source_id' => $order->id, 'created_at' => $placed,
                ]);
            }
        }

        $this->happenings($order, $placed, $failedAt, $paidAt, $made, $weeks, $collect);

        return $order;
    }

    /**
     * What is bought: mostly one piece, sometimes a few, and small things in twos and threes.
     *
     * @return list<array{variant: ProductVariant, quantity: int}>
     */
    private function lines(bool $open): array
    {
        $lines = [];
        $count = $this->weighted([1 => 62, 2 => 28, 3 => 10]);

        for ($tries = 0; count($lines) < $count && $tries < 12; $tries++) {
            /** @var ProductVariant $variant */
            $variant = $this->variants[$this->random->getInt(0, $this->variants->count() - 1)];
            $quantity = $variant->price <= 12_000_00 ? $this->weighted([1 => 50, 2 => 35, 3 => 10, 4 => 5]) : 1;

            if (isset($lines[$variant->id])) {
                continue;
            }

            // An order still on its way holds real stock, so it can only hold what is there.
            if ($open && ! $variant->made_to_order && ($this->stock[$variant->id] ?? 0) - 1 < $quantity) {
                continue;
            }

            $lines[$variant->id] = ['variant' => $variant, 'quantity' => $quantity];
        }

        return array_values($lines);
    }

    private function statusFor(int $age, bool $made, int $weeks, bool $collect): OrderStatus
    {
        // Something made to order is in the workshop for its lead time, whatever else is in the order.
        if ($made && $age < $weeks * 7 - 3) {
            return $this->chance(4) ? OrderStatus::Cancelled : ($age === 0 ? OrderStatus::Paid : OrderStatus::InProduction);
        }

        $stage = match (true) {
            $age >= self::OPEN_DAYS => $this->weighted(['delivered' => 88, 'cancelled' => 5, 'expired' => 7]),
            $age >= 5 => $this->weighted(['delivered' => 68, 'out' => 16, 'ready' => 8, 'cancelled' => 3, 'expired' => 5]),
            $age >= 1 => $this->weighted(['paid' => 22, 'ready' => 30, 'out' => 22, 'delivered' => 16, 'cancelled' => 3, 'expired' => 7]),
            default => $this->weighted(['paid' => 66, 'ready' => 22, 'expired' => 12]),
        };

        return match ($stage) {
            'paid' => OrderStatus::Paid,
            'ready' => OrderStatus::Ready,
            'out' => $collect ? OrderStatus::Ready : OrderStatus::OutForDelivery,
            'delivered' => OrderStatus::Delivered,
            'cancelled' => OrderStatus::Cancelled,
            default => OrderStatus::Expired,
        };
    }

    /** The order's payments and its history, each at a time that fits where the order has got to. */
    private function happenings(Order $order, Carbon $placed, ?Carbon $failedAt, ?Carbon $paidAt, bool $made, int $weeks, bool $collect): void
    {
        $event = fn (OrderEventType $type, Carbon $at, ?string $note = null, ?User $by = null) => OrderEvent::query()->forceCreate([
            'order_id' => $order->id, 'type' => $type, 'note' => $note, 'user_id' => $by?->id, 'created_at' => $at,
        ]);

        $event(OrderEventType::Placed, $placed);

        if ($order->status === OrderStatus::PendingPayment) {
            return;
        }

        if ($failedAt !== null) {
            [$state, $code, $said] = $this->pick([
                [PaymentState::Cancelled, '1032', 'Request cancelled by user'],
                [PaymentState::TimedOut, '1037', 'DS timeout user cannot be reached'],
                [PaymentState::Failed, '1', 'The balance is insufficient for the transaction'],
            ]);
            $this->payment($order, $state, $failedAt, $code, $said);
            $event(OrderEventType::PaymentFailed, $failedAt, $state->getLabel());
        }

        if ($paidAt === null) {
            $closed = $order->status === OrderStatus::Cancelled
                ? $placed->copy()->addMinutes($this->random->getInt(6, 14))
                : $placed->copy()->addMinutes((int) config('store.hold_minutes'))->addSeconds($this->random->getInt(5, 55));

            $order->status === OrderStatus::Cancelled
                ? $event(OrderEventType::Cancelled, $closed, $this->pick(self::CANCEL_REASONS), $this->fulfiller)
                : $event(OrderEventType::Expired, $closed);

            $order->forceFill(['closed_at' => $closed, 'updated_at' => $closed])->saveQuietly();

            return;
        }

        $payment = $this->payment($order, PaymentState::Paid, $paidAt, '0', 'The service request is processed successfully.');
        $event(OrderEventType::Paid, $paidAt, 'M-Pesa, receipt '.$payment->receipt);

        if ($order->status === OrderStatus::Cancelled) {
            $closed = $paidAt->copy()->addHours($this->random->getInt(2, 30));
            $event(OrderEventType::Cancelled, $closed, $this->pick(self::CANCEL_REASONS), $this->fulfiller);
            $event(OrderEventType::RefundDue, $closed, CancelOrder::owed($payment->charged, $payment->phone));
            // Refunds older than a few days have been sent; a recent one is still to do.
            if ($closed->lt(now()->subDays(3))) {
                $event(OrderEventType::RefundSent, $closed->copy()->addHours($this->random->getInt(3, 20)), CancelOrder::owed($payment->charged, $payment->phone).', M-Pesa '.$this->receipt());
            }
            $order->forceFill(['closed_at' => $closed, 'updated_at' => $closed])->saveQuietly();

            return;
        }

        // Each step staff took, as far as the order has got, never later than now.
        $reached = array_search($order->status, [OrderStatus::Paid, OrderStatus::InProduction, OrderStatus::Ready, OrderStatus::OutForDelivery, OrderStatus::Delivered], true);
        $at = $paidAt->copy();
        $steps = array_filter([
            $made ? [1, OrderEventType::InProduction, fn () => $this->random->getInt(2, 20)] : null,
            [2, OrderEventType::Ready, fn () => $made ? $weeks * 7 * 24 - $this->random->getInt(24, 120) : $this->random->getInt(3, 26)],
            $collect ? null : [3, OrderEventType::OutForDelivery, fn () => $this->random->getInt(14, 40)],
            [4, OrderEventType::Delivered, fn () => $collect ? $this->random->getInt(5, 60) : $this->random->getInt(4, 30)],
        ]);

        foreach ($steps as [$rank, $type, $hours]) {
            if ($rank > $reached) {
                break;
            }

            $next = $at->copy()->addHours($hours());
            // An order cannot have been moved on later than this minute, whatever the dice said.
            $at = $next->gt(now()) ? $at->copy()->addMinutes(max(1, (int) ($at->diffInMinutes(now()) / 2))) : $next;
            $event($type, $at, null, $this->fulfiller);
        }

        $order->forceFill(['closed_at' => $order->status === OrderStatus::Delivered ? $at : null, 'updated_at' => $at])->saveQuietly();
    }

    private function payment(Order $order, PaymentState $state, Carbon $at, string $code, string $said): Payment
    {
        $id = Str::lower(Str::random(6)).'-'.$order->id.'-'.$this->random->getInt(1000, 9999);

        return Payment::query()->forceCreate([
            'order_id' => $order->id, 'method' => PaymentMethod::Mpesa, 'state' => $state, 'amount' => $order->total, 'charged' => $order->total,
            'phone' => $order->phone, 'merchant_request_id' => 'demo-'.$id, 'checkout_request_id' => 'ws_CO_demo_'.$id,
            'receipt' => $state === PaymentState::Paid ? $this->receipt() : null, 'result_code' => $code, 'result_description' => $said,
            'settled_at' => $at, 'created_at' => $at->copy()->subSeconds($this->random->getInt(8, 20)), 'updated_at' => $at,
        ]);
    }

    /** A receipt number in the shape M-Pesa gives them. */
    private function receipt(): string
    {
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789';

        return 'U'.implode('', array_map(fn () => $letters[$this->random->getInt(0, strlen($letters) - 1)], range(1, 9)));
    }

    /**
     * What the customers of the latest orders were written, so the message log has something to read.
     *
     * @param  list<Order>  $orders
     */
    private function messagesFor(array $orders): void
    {
        foreach (array_slice($orders, -18) as $order) {
            $order->load('events', 'items', 'payments');

            foreach ($order->events as $happened) {
                $before = Message::query()->max('id') ?? 0;

                ($this->tell)($order, $happened->type, $happened->type === OrderEventType::Cancelled ? $happened->note : null);

                Message::query()->where('id', '>', $before)->toBase()->update(['created_at' => $happened->created_at, 'updated_at' => $happened->created_at]);
            }
        }
    }

    private function chance(int $percent): bool
    {
        return $this->random->getInt(1, 100) <= $percent;
    }

    /**
     * @template T
     *
     * @param  list<T>  $from
     * @return T
     */
    private function pick(array $from): mixed
    {
        return $from[$this->random->getInt(0, count($from) - 1)];
    }

    /**
     * One key of the array, each as likely as its number says.
     *
     * @template K of array-key
     *
     * @param  array<K, int>  $shares
     * @return K
     */
    private function weighted(array $shares): int|string
    {
        $roll = $this->random->getInt(1, array_sum($shares));

        foreach ($shares as $key => $share) {
            if (($roll -= $share) <= 0) {
                return $key;
            }
        }

        return array_key_last($shares);
    }
}
