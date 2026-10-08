<?php

namespace Database\Seeders;

use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\ResolveSalonClient;
use App\Actions\Salon\AddHoliday;
use App\Actions\Staff\AddTimeOff;
use App\Actions\Staff\LinkStaffProfile;
use App\Actions\Staff\ReplaceWeeklySchedule;
use App\Actions\Staff\SaveStaff;
use App\Actions\Staff\UpdateStaffPhoto;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\ScheduleAccess;
use App\Enums\TimeOffReason;
use App\Exceptions\BookingException;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Support\CurrentSalon;
use Database\Seeders\Support\DemoImages;
use Illuminate\Database\Seeder;

/**
 * Professionals, working hours, time off and holidays for the demo salons.
 * Called by DemoSeeder right after it creates them.
 */
class DemoScheduleSeeder extends Seeder
{
    private const array MONDAY_TO_SATURDAY = [1, 2, 3, 4, 5, 6];

    private const array TUESDAY_TO_SATURDAY = [2, 3, 4, 5, 6];

    private const int SATURDAY = 6;

    /** The portrait of each demo owner's own professional profile, by salon slug. */
    private const array OWNER_PORTRAITS = [
        'barberia-don-lucho' => 1836983,
        'studio-bella' => 3993312,
        'corte-color-surco' => 7518731,
        'nails-spa-barranco' => 3422099,
    ];

    public function __construct(
        private readonly SaveStaff $saveStaff,
        private readonly UpdateStaffPhoto $staffPhoto,
        private readonly LinkStaffProfile $linkStaffProfile,
        private readonly ReplaceWeeklySchedule $replaceSchedule,
        private readonly AddTimeOff $addTimeOff,
        private readonly AddHoliday $addHoliday,
        private readonly CreateBooking $createBooking,
        private readonly ResolveSalonClient $resolveClient,
        private readonly CurrentSalon $currentSalon,
    ) {}

    public function run(): void
    {
        foreach (Salon::query()->orderBy('id')->get() as $salon) {
            $this->currentSalon->set($salon);

            // The owner's profile was created together with the salon.
            foreach (Staff::query()->get() as $owner) {
                $this->schedule($owner, self::MONDAY_TO_SATURDAY, [['09:00', '13:00'], ['15:00', '20:00']]);
                $this->staffPhoto->handle($owner, DemoImages::portrait(self::OWNER_PORTRAITS[$salon->slug] ?? 7518731));
            }

            $this->addHoliday->handle($salon, $salon->localNow()->month(12)->day(25)->toDateString(), 'Navidad');
        }

        $barbershop = Salon::query()->where('slug', 'barberia-don-lucho')->firstOrFail();
        $this->barbershopTeam($barbershop);
        $this->barbershopBookings($barbershop);
        $this->currentSalon->set(null);
    }

    /**
     * A barber with VIP hours and a vacation, an apprentice in the
     * afternoons and a barber who does not use the app.
     */
    private function barbershopTeam(Salon $salon): void
    {
        $this->currentSalon->set($salon);

        $pedro = $this->linkStaffProfile->handle($salon, User::where('email', 'personal@peluweb.test')->firstOrFail());
        $pedro->update(['title' => 'Fades y barba']);
        $this->staffPhoto->handle($pedro, DemoImages::portrait(897265));
        $this->schedule($pedro, self::TUESDAY_TO_SATURDAY, [['10:00', '19:00']], [
            ['weekday' => self::SATURDAY, 'start' => '19:00', 'end' => '21:00', 'access' => ScheduleAccess::Vip],
        ]);

        $nextMonday = $salon->localNow()->next('monday')->startOfDay();
        $this->addTimeOff->handle($pedro, $nextMonday, $nextMonday->addDays(3), TimeOffReason::Vacation, 'Viaje familiar');

        $kevin = $this->linkStaffProfile->handle($salon, User::where('email', 'aprendiz@peluweb.test')->firstOrFail());
        $kevin->update(['title' => 'Aprendiz']);
        $this->staffPhoto->handle($kevin, DemoImages::portrait(4625648));
        $this->schedule($kevin, [1, 2, 3, 4, 5], [['14:00', '20:00']]);

        $martin = $this->saveStaff->handle($salon, null, ['name' => 'Martín Soto', 'title' => 'Barbero clásico']);
        $this->staffPhoto->handle($martin, DemoImages::portrait(18483774));
        $this->schedule($martin, [1, 3, 5, self::SATURDAY], [['09:00', '17:00']]);
    }

    /**
     * The barbershop asks for a deposit, and the demo client already has a
     * visit booked, so the agenda and "Mis reservas" are not empty.
     */
    private function barbershopBookings(Salon $salon): void
    {
        $salon->forceFill([
            'deposit_percent' => 30,
            'payment_instructions' => 'Yape o Plin al 987 654 321, a nombre de Luis Quispe.',
        ])->save();

        $client = User::where('email', 'cliente@peluweb.test')->first();
        $service = Service::query()->where('is_addon', false)->orderBy('position')->first();

        if ($client === null || $service === null) {
            return;
        }

        // The first working day from tomorrow on, at 11:00 on the salon's clock.
        foreach (range(1, 7) as $daysAhead) {
            try {
                $this->createBooking->handle(new NewBooking(
                    salon: $salon,
                    client: $this->resolveClient->forUser($salon, $client),
                    selections: [['service_id' => $service->id, 'variant_id' => $service->variants()->value('id')]],
                    staff: null,
                    startsAt: $salon->localNow()->addDays($daysAhead)->setTime(11, 0)->utc(),
                    source: BookingSource::Staff,
                    note: 'Degradado bajo, como la última vez',
                ));

                return;
            } catch (BookingException) {
                continue;
            }
        }
    }

    /**
     * @param  list<int>  $weekdays
     * @param  list<array{string, string}>  $spans  public ranges repeated on every weekday
     * @param  list<array{weekday: int, start: string, end: string, access: ScheduleAccess}>  $extra
     */
    private function schedule(Staff $staff, array $weekdays, array $spans, array $extra = []): void
    {
        $ranges = $extra;

        foreach ($weekdays as $weekday) {
            foreach ($spans as [$start, $end]) {
                $ranges[] = ['weekday' => $weekday, 'start' => $start, 'end' => $end, 'access' => ScheduleAccess::Public];
            }
        }

        $this->replaceSchedule->handle($staff, $ranges);
    }
}
