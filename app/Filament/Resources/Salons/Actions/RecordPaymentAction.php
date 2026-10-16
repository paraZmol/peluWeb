<?php

namespace App\Filament\Resources\Salons\Actions;

use App\Actions\Billing\RecordSubscriptionPayment;
use App\Data\Billing\NewSubscriptionPayment;
use App\Enums\PaymentMethod;
use App\Exceptions\BillingException;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Exceptions\Halt;

/**
 * "Registrar pago": a subscription payment the platform admin received by
 * Yape or transfer. The amount is proposed from the plan and the months,
 * and can be changed (a price agreed by hand).
 */
class RecordPaymentAction
{
    public static function make(): Action
    {
        return Action::make('recordPayment')
            ->label('Registrar pago')
            ->icon('heroicon-o-banknotes')
            ->modalHeading('Registrar pago de la suscripción')
            ->visible(fn (Salon $record): bool => ! $record->trashed())
            ->fillForm(fn (Salon $record): array => self::defaults($record))
            ->schema([
                Select::make('plan_id')
                    ->label('Plan')
                    ->options(fn (): array => Plan::query()->orderBy('position')->pluck('name', 'id')->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set, Salon $record) => self::proposeAmount($get, $set, $record)),
                Radio::make('months')
                    ->label('Periodo')
                    ->options([1 => '1 mes', Plan::YEAR_MONTHS => '1 año (paga 10 meses)'])
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set, Salon $record) => self::proposeAmount($get, $set, $record)),
                TextInput::make('amount')
                    ->label('Monto recibido (S/)')
                    ->required()
                    ->regex('/^\d{1,5}(\.\d{1,2})?$/'),
                Select::make('method')
                    ->label('Cómo pagó')
                    ->options([
                        PaymentMethod::Yape->value => PaymentMethod::Yape->label(),
                        PaymentMethod::Plin->value => PaymentMethod::Plin->label(),
                        PaymentMethod::Transfer->value => PaymentMethod::Transfer->label(),
                        PaymentMethod::Cash->value => PaymentMethod::Cash->label(),
                    ])
                    ->required(),
                TextInput::make('note')
                    ->label('Nota (opcional)')
                    ->maxLength(SubscriptionPayment::NOTE_MAX_LENGTH),
            ])
            ->action(function (array $data, Salon $record): void {
                /** @var User $admin */
                $admin = auth()->user();

                try {
                    $payment = app(RecordSubscriptionPayment::class)->handle(new NewSubscriptionPayment(
                        salon: $record,
                        plan: Plan::query()->findOrFail((int) $data['plan_id']),
                        months: (int) $data['months'],
                        amountCents: Money::toCents((string) $data['amount']),
                        method: PaymentMethod::from($data['method']),
                        recordedBy: $admin,
                        note: filled($data['note'] ?? null) ? (string) $data['note'] : null,
                    ));
                } catch (BillingException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    throw new Halt;
                }

                $record->refresh();
                Notification::make()->success()
                    ->title('Pago registrado: cubre hasta el '.$payment->covers_until->format('d/m/Y').'.')
                    ->send();
            });
    }

    /**
     * @return array{plan_id: int, months: int, amount: string}
     */
    private static function defaults(Salon $salon): array
    {
        $plan = $salon->currentPlan();

        return [
            'plan_id' => $plan->id,
            'months' => 1,
            'amount' => Money::toAmount(RecordSubscriptionPayment::expectedCents($salon, $plan, 1)),
        ];
    }

    private static function proposeAmount(Get $get, Set $set, Salon $salon): void
    {
        $plan = Plan::query()->find((int) $get('plan_id'));
        $months = (int) $get('months');

        if ($plan !== null && in_array($months, [1, Plan::YEAR_MONTHS], true)) {
            $set('amount', Money::toAmount(RecordSubscriptionPayment::expectedCents($salon, $plan, $months)));
        }
    }
}
