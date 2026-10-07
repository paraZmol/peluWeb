<?php

namespace App\Actions\Salon;

use App\Actions\Catalog\AddServicesFromTemplates;
use App\Actions\Staff\SaveStaff;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\SalonRole;
use App\Enums\SalonStatus;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\ServiceTemplate;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSalon
{
    public function __construct(
        private readonly AddServicesFromTemplates $addServices,
        private readonly SaveStaff $saveStaff,
    ) {}

    /**
     * Create a salon in trial and make the given user its owner.
     */
    public function handle(
        User $owner,
        string $name,
        BusinessType $businessType,
        Clientele $clientele,
        ?string $phone = null,
    ): Salon {
        return DB::transaction(function () use ($owner, $name, $businessType, $clientele, $phone): Salon {
            $salon = new Salon([
                'name' => $name,
                'business_type' => $businessType,
                'clientele' => $clientele,
                'phone' => $phone,
            ]);
            $salon->slug = $this->uniqueSlug($name);
            $salon->status = SalonStatus::Trial;
            $salon->trial_ends_at = now()->addDays(Salon::TRIAL_DAYS);
            $salon->save();

            $membership = new SalonMember;
            $membership->salon()->associate($salon);
            $membership->user()->associate($owner);
            $membership->role = SalonRole::Owner;
            $membership->save();

            $this->addStarterServices($salon);

            // Most owners of small salons also attend clients themselves.
            $this->saveStaff->handle($salon, null, ['name' => Staff::nameFrom($owner->name)], $owner->id);

            return $salon;
        });
    }

    /**
     * A new salon starts with the catalog entries of its type, so the owner
     * removes what they do not offer instead of typing everything.
     */
    private function addStarterServices(Salon $salon): void
    {
        $templates = ServiceTemplate::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (ServiceTemplate $template) => $salon->business_type->startsWith($template->code))
            ->values();

        $this->addServices->handle($salon, $templates);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'salon';
        $slug = $base;

        while (Salon::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
