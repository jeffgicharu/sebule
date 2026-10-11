<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentState;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Orders\CancelOrder;
use App\Orders\MoveOrderOn;
use App\Orders\OrderCannotMove;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * One order: what was bought, who by, how it was paid for and everything that
 * has happened to it. Staff move it on and cancel it from here.
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Order '.$this->getRecord()->number;
    }

    public function getSubheading(): ?string
    {
        /** @var Order $order */
        $order = $this->getRecord();

        return $order->customer_name.', placed '.$order->created_at->format('j F Y \a\t H:i');
    }

    protected function getHeaderActions(): array
    {
        return [
            // One action for each step, so a button always does what it said when it was pressed,
            // even if somebody else moved the order in the meantime.
            ...array_map($this->step(...), [OrderStatus::InProduction, OrderStatus::Ready, OrderStatus::OutForDelivery, OrderStatus::Delivered]),

            Action::make('cancel')
                ->label('Cancel order')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->outlined()
                ->visible(fn (Order $record): bool => auth()->user()->can('cancel', $record))
                ->modalHeading('Cancel this order?')
                ->modalDescription(fn (Order $record): string => self::whatCancellingDoes($record))
                ->schema([
                    Textarea::make('reason')
                        ->label('Reason')
                        ->helperText($this->getRecord()->email ? 'The customer reads this in the email they are sent.' : 'Kept in the history of the order.')
                        ->required()
                        ->maxLength(200)
                        ->rows(3),
                ])
                ->modalSubmitActionLabel('Cancel the order')
                ->modalCancelActionLabel('Keep it')
                ->action(function (Order $record, array $data, CancelOrder $cancel): void {
                    try {
                        $cancel($record, trim($data['reason']), auth()->user());
                    } catch (OrderCannotMove $problem) {
                        $this->refused($problem);

                        return;
                    }

                    $this->changed("Order {$record->number} is cancelled.");
                }),

            Action::make('customerPage')
                ->label("Customer's page")
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Order $record): string => route('orders.show', $record), shouldOpenInNewTab: true),
        ];
    }

    private function step(OrderStatus $to): Action
    {
        return Action::make('to_'.$to->value)
            ->label(fn (Order $record): string => self::verb($to, $record))
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->visible(fn (Order $record): bool => $record->nextStatus() === $to && auth()->user()->can('advance', $record))
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record): string => self::verb($to, $record).'?')
            ->modalDescription(fn (Order $record): string => $record->email
                ? "The customer is told by email at {$record->email}, and their order page shows it straight away."
                : 'The customer left no email address. Their order page shows it straight away.')
            ->modalSubmitActionLabel(fn (Order $record): string => self::verb($to, $record))
            ->action(function (Order $record, MoveOrderOn $move) use ($to): void {
                try {
                    $move($record, $to, auth()->user());
                } catch (OrderCannotMove $problem) {
                    $this->refused($problem);

                    return;
                }

                $this->changed("Order {$record->number} is now: ".mb_strtolower($record->refresh()->status->getLabel()).'.');
            });
    }

    /** What the button for a step says. */
    public static function verb(OrderStatus $to, Order $order): string
    {
        return match ($to) {
            OrderStatus::InProduction => 'Start making it',
            OrderStatus::Ready => 'Mark as ready',
            OrderStatus::OutForDelivery => 'Send out for delivery',
            OrderStatus::Delivered => $order->delivery_method === DeliveryMethod::Delivery ? 'Mark as delivered' : 'Mark as collected',
            default => $to->getLabel(),
        };
    }

    private static function whatCancellingDoes(Order $order): string
    {
        $paid = $order->payments->firstWhere('state', PaymentState::Paid);

        if ($paid !== null && $paid->charged > 0) {
            return 'This order is paid for. Cancelling it puts its pieces back on sale and tells the customer their money is coming back, but it does not send the money: send '
                .CancelOrder::owed($paid->charged, $paid->phone).' by M-Pesa yourself.';
        }

        return 'Its pieces go back on sale and the customer is told. '.($order->status->isPaid() ? 'No money was taken for it.' : 'It has not been paid for ('.Money::format($order->total).').');
    }

    private function changed(string $message): void
    {
        $this->getRecord()->refresh()->unsetRelations();

        Notification::make()->title($message)->success()->send();
    }

    private function refused(OrderCannotMove $problem): void
    {
        $this->getRecord()->refresh()->unsetRelations();

        Notification::make()
            ->title('Nothing was changed')
            ->body('Somebody else changed this order first. It is now: '.mb_strtolower($problem->order->status->getLabel()).'.')
            ->warning()
            ->send();
    }
}
