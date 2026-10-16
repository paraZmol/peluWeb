<?php

namespace App\Actions\Complaints;

use App\Models\Complaint;
use App\Notifications\ComplaintReceivedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Files a sheet in the platform's Libro de Reclamaciones: next number of
 * the year, the provider as it reads today, the deadline in business days,
 * and a full copy to the person's email.
 */
class FileComplaint
{
    /**
     * @param  array<string, mixed>  $sheet  validated fields of the form (StoreComplaintRequest)
     */
    public function handle(array $sheet): Complaint
    {
        $filedAt = CarbonImmutable::now();
        $localDate = Complaint::localToday();
        $year = (int) $localDate->format('Y');

        // Outside the transaction: an INSERT IGNORE that meets the row takes a
        // shared lock, and two of them upgrading to FOR UPDATE deadlock.
        DB::table('complaint_counters')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

        $complaint = DB::transaction(function () use ($sheet, $filedAt, $localDate, $year): Complaint {
            return Complaint::query()->create([
                ...$sheet,
                'year' => $year,
                'number' => $this->nextNumber($year),
                'provider_name' => (string) config('legal.provider.name'),
                'provider_ruc' => (string) config('legal.provider.ruc'),
                'provider_address' => (string) config('legal.provider.address'),
                'consented_at' => $filedAt,
                'due_on' => Complaint::businessDays()->addTo($localDate, (int) config('legal.complaints.response_business_days'))->format('Y-m-d'),
            ]);
        });

        Notification::route('mail', $complaint->email)->notify(new ComplaintReceivedNotification($complaint));

        return $complaint;
    }

    /**
     * The year's row is locked until the sheet is saved: two sheets filed at
     * once wait for each other, and a failed save gives its number back.
     */
    private function nextNumber(int $year): int
    {
        $last = (int) DB::table('complaint_counters')->where('year', $year)->lockForUpdate()->value('last_number');

        DB::table('complaint_counters')->where('year', $year)->update(['last_number' => $last + 1]);

        return $last + 1;
    }
}
