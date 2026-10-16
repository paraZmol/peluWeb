<?php

namespace App\Actions\Complaints;

use App\Exceptions\ComplaintException;
use App\Models\Complaint;
use App\Models\User;
use App\Notifications\ComplaintAnsweredNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The platform's answer to a sheet: written once, dated, and sent to the
 * person's email. Two admins answering at the same time: the second one is
 * told it already has an answer.
 */
class RespondToComplaint
{
    public function handle(Complaint $complaint, User $admin, string $response): Complaint
    {
        $answered = DB::transaction(function () use ($complaint, $admin, $response): Complaint {
            $locked = Complaint::query()->lockForUpdate()->findOrFail($complaint->id);

            if ($locked->isAnswered()) {
                throw ComplaintException::alreadyAnswered();
            }

            $locked->forceFill([
                'response' => $response,
                'responded_at' => CarbonImmutable::now(),
                'responded_by' => $admin->id,
            ])->save();

            return $locked;
        });

        Notification::route('mail', $answered->email)->notify(new ComplaintAnsweredNotification($answered));

        return $answered;
    }
}
