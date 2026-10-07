<?php

namespace Database\Seeders;

use App\Actions\Salon\AddHoliday;
use App\Actions\Staff\AddTimeOff;
use App\Actions\Staff\LinkStaffProfile;
use App\Actions\Staff\ReplaceWeeklySchedule;
use App\Actions\Staff\SaveStaff;
use App\Enums\ScheduleAccess;
use App\Enums\TimeOffReason;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use App\Support\CurrentSalon;
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

    public function __construct(
        private readonly SaveStaff $saveStaff,
        private readonly LinkStaffProfile $linkStaffProfile,
        private readonly ReplaceWeeklySchedule $replaceSchedule,
        private readonly AddTimeOff $addTimeOff,
        private readonly AddHoliday $addHoliday,
        private readonly CurrentSalon $currentSalon,
    ) {}

    public function run(): void
    {
        foreach (Salon::query()->orderBy('id')->get() as $salon) {
            $this->currentSalon->set($salon);

            // The owner's profile was created together with the salon.
            foreach (Staff::query()->get() as $owner) {
                $this->schedule($owner, self::MONDAY_TO_SATURDAY, [['09:00', '13:00'], ['15:00', '20:00']]);
            }

            $this->addHoliday->handle($salon, $salon->localNow()->month(12)->day(25)->toDateString(), 'Navidad');
        }

        $this->barbershopTeam(Salon::query()->where('slug', 'barberia-don-lucho')->firstOrFail());
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
        $this->schedule($pedro, self::TUESDAY_TO_SATURDAY, [['10:00', '19:00']], [
            ['weekday' => self::SATURDAY, 'start' => '19:00', 'end' => '21:00', 'access' => ScheduleAccess::Vip],
        ]);

        $nextMonday = $salon->localNow()->next('monday')->startOfDay();
        $this->addTimeOff->handle($pedro, $nextMonday, $nextMonday->addDays(3), TimeOffReason::Vacation, 'Viaje familiar');

        $kevin = $this->linkStaffProfile->handle($salon, User::where('email', 'aprendiz@peluweb.test')->firstOrFail());
        $kevin->update(['title' => 'Aprendiz']);
        $this->schedule($kevin, [1, 2, 3, 4, 5], [['14:00', '20:00']]);

        $martin = $this->saveStaff->handle($salon, null, ['name' => 'Martín Soto', 'title' => 'Barbero clásico']);
        $this->schedule($martin, [1, 3, 5, self::SATURDAY], [['09:00', '17:00']]);
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
