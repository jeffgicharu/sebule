<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The day at a glance: what has sold, and what is waiting on staff or on a
 * customer. Sales count orders by the day they were paid for, and leave out
 * orders cancelled since.
 */
class SalesOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        [$today, $todayOrders] = self::sales(now()->startOfDay(), now());
        [$month, $monthOrders] = self::sales(now()->startOfMonth(), now());
        [$lastMonth] = self::sales(now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow());

        $toPrepare = Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::InProduction])->count();
        $onTheMove = Order::query()->whereIn('status', [OrderStatus::Ready, OrderStatus::OutForDelivery])->count();
        $unpaid = Order::query()->where('status', OrderStatus::PendingPayment)->count();

        return [
            Stat::make('Sales today', Money::format($today))
                ->description($todayOrders.' '.str('order')->plural($todayOrders).' paid for'),
            Stat::make('Sales this month', Money::format($month))
                ->description($monthOrders.' '.str('order')->plural($monthOrders).'. '.Money::format($lastMonth).' by this day last month'),
            Stat::make('Orders to prepare', (string) $toPrepare)
                ->description($onTheMove.' ready or out for delivery')
                ->url(OrderResource::getUrl('index', ['activeTab' => 'prepare'])),
            Stat::make('Awaiting payment', (string) $unpaid)
                ->description('Placed, with stock held, and not paid for yet')
                ->url(OrderResource::getUrl('index', ['activeTab' => 'unpaid'])),
        ];
    }

    /**
     * What was sold between two moments, and in how many orders.
     *
     * @return array{0: int, 1: int}
     */
    public static function sales(Carbon $from, Carbon $until): array
    {
        $sold = Order::query()
            ->whereBetween('paid_at', [$from, $until])
            ->where(fn (Builder $query) => $query->where('status', '!=', OrderStatus::Cancelled))
            ->selectRaw('coalesce(sum(total), 0) as total, count(*) as orders')
            ->toBase()
            ->first();

        return [(int) $sold->total, (int) $sold->orders];
    }
}
