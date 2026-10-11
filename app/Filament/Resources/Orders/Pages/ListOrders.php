<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /**
     * The order list cut the way the work is done: what to prepare, what is
     * ready to go, what is on the road, and what is still owed.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = Order::query()->selectRaw('status, count(*) as orders')->groupBy('status')->pluck('orders', 'status');
        $count = fn (OrderStatus ...$statuses): ?int => array_sum(array_map(fn (OrderStatus $status) => (int) ($counts[$status->value] ?? 0), $statuses)) ?: null;
        $in = fn (OrderStatus ...$statuses) => fn (Builder $query) => $query->whereIn('status', $statuses);

        return [
            'all' => Tab::make('All'),
            'prepare' => Tab::make('To prepare')
                ->badge($count(OrderStatus::Paid, OrderStatus::InProduction))
                ->modifyQueryUsing($in(OrderStatus::Paid, OrderStatus::InProduction)),
            'ready' => Tab::make('Ready')
                ->badge($count(OrderStatus::Ready))
                ->modifyQueryUsing($in(OrderStatus::Ready)),
            'out' => Tab::make('Out for delivery')
                ->badge($count(OrderStatus::OutForDelivery))
                ->modifyQueryUsing($in(OrderStatus::OutForDelivery)),
            'unpaid' => Tab::make('Awaiting payment')
                ->badge($count(OrderStatus::PendingPayment))
                ->badgeColor('warning')
                ->modifyQueryUsing($in(OrderStatus::PendingPayment)),
            'closed' => Tab::make('Finished')
                ->modifyQueryUsing($in(OrderStatus::Delivered, OrderStatus::Cancelled, OrderStatus::Expired)),
        ];
    }
}
