<?php

namespace Database\Seeders;

use App\Actions\Accounting\ManageExpenseCategories;
use App\Actions\Salon\ManageSalonPhotos;
use App\Actions\Salon\UpdateSalonBranding;
use App\Actions\Staff\UpdateStaffPhoto;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\SalonPermission;
use App\Enums\ServiceCategory;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\Support\DemoImages;
use Illuminate\Database\Seeder;
use LogicException;

/**
 * Known accounts for the Playwright end-to-end tests (tests/e2e).
 * Only ever run against the test database.
 */
class E2eSeeder extends Seeder
{
    public const string PASSWORD = 'password';

    public function run(): void
    {
        // Its accounts share a public password: never on a real server.
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('E2eSeeder only runs in local and testing.');
        }

        User::factory()->create([
            'name' => 'Rosa Quispe',
            'email' => 'duena@example.com',
        ]);

        // Owner of two salons (switcher) and a staff member (no team access).
        $multi = User::factory()->create(['name' => 'Marta Multi', 'email' => 'multi@example.com']);
        $first = Salon::factory()->ownedBy($multi)->create(['name' => 'Salón Uno', 'slug' => 'salon-uno']);
        Staff::factory()->for($first)->linkedTo($multi)->create(['name' => 'Marta Multi']);
        $alwaysOpen = Staff::factory()->for($first)->working([1, 2, 3, 4, 5, 6, 7], '00:00', '23:55')->create(['name' => 'Siempre Abierto']);

        // Something to book online, and a client account to book it with.
        $cut = Service::factory()->for($first)->create(['name' => 'Corte de prueba', 'price_cents' => 3500, 'active_minutes' => 30]);
        $alwaysOpen->services()->attach($cut->id);
        User::factory()->create(['name' => 'Carla Cliente', 'email' => 'cliente@example.com']);
        $second = Salon::factory()->ownedBy($multi)->create(['name' => 'Salón Dos Ñandú', 'slug' => 'salon-dos']);

        $staff = User::factory()->create(['name' => 'Pedro Personal', 'email' => 'personal@example.com']);
        SalonMember::factory()->for($second)->for($staff)->create();
        Staff::factory()->for($second)->linkedTo($staff)->create(['name' => 'Pedro Personal']);

        $showcase = $this->seedShowcaseSalon();
        $till = $this->seedTillSalon();

        // Factories skip what CreateSalon gives every real salon.
        foreach ([$first, $second, $showcase, $till] as $salon) {
            app(ManageExpenseCategories::class)->createDefaults($salon);
        }
    }

    /**
     * A salon to try the money rules by role: a professional who attends
     * visits but has no cash permission (no discounts, no cash screens) and
     * one with the cash and reports permissions. Always-open agendas, so a
     * visit can be booked for right now.
     */
    private function seedTillSalon(): Salon
    {
        $owner = User::factory()->create(['name' => 'Olga Obradora', 'email' => 'caja-duena@example.com']);
        $salon = Salon::factory()->ownedBy($owner)->create(['name' => 'Salón Caja', 'slug' => 'salon-caja']);
        $cut = Service::factory()->for($salon)->create(['name' => 'Corte de caja', 'price_cents' => 3500, 'active_minutes' => 30]);

        $withoutCash = User::factory()->create(['name' => 'Sara Sin Caja', 'email' => 'sin-caja@example.com']);
        SalonMember::factory()->for($salon)->for($withoutCash)->create();
        $withCash = User::factory()->create(['name' => 'Cleo Con Caja', 'email' => 'con-caja@example.com']);
        SalonMember::factory()->for($salon)->for($withCash)->withPermissions(SalonPermission::ManageCash, SalonPermission::ViewReports)->create();

        foreach ([$withoutCash, $withCash] as $user) {
            Staff::factory()->for($salon)->linkedTo($user)->working([1, 2, 3, 4, 5, 6, 7], '00:00', '23:55')
                ->create(['name' => $user->name])->services()->attach($cut->id);
        }

        return $salon;
    }

    /**
     * A fuller salon (brand color, several categories, variants, a deposit) to
     * look at the screens with realistic content. Independent from the salons
     * above, so the flows that assert on them are not affected.
     */
    private function seedShowcaseSalon(): Salon
    {
        $owner = User::factory()->create(['name' => 'Valeria Vitrina', 'email' => 'vitrina@example.com']);
        $salon = Salon::factory()->ownedBy($owner)->create([
            'name' => 'Estudio Ñusta',
            'slug' => 'estudio-vitrina',
            'business_type' => BusinessType::Unisex,
            'clientele' => Clientele::Everyone,
            'brand_color' => '#1F6F64',
            'description' => 'Cortes, color y peinados para todo público en el corazón de Miraflores. Atención con cita.',
            'address' => 'Av. Larco 1234, int. 2',
            'district' => 'Miraflores',
            'city' => 'Lima',
            'deposit_percent' => 30,
            'payment_instructions' => 'Yape o Plin al 987 654 321 a nombre de Estudio Ñusta.',
        ]);

        $everyDay = [1, 2, 3, 4, 5, 6, 7];
        $barber = Staff::factory()->for($salon)->working($everyDay, '09:00', '20:00')
            ->create(['name' => 'José Antonio Fernández', 'title' => 'Barbero sénior']);
        $colorist = Staff::factory()->for($salon)->working($everyDay, '09:00', '20:00')
            ->create(['name' => 'Lucía Ramírez', 'title' => 'Colorista']);
        $valeria = Staff::factory()->for($salon)->linkedTo($owner)->create(['name' => 'Valeria Vitrina']);
        $this->showcasePhotos($salon, [$barber, 18483774], [$colorist, 3992875], [$valeria, 3993312]);

        $catalog = [
            [ServiceCategory::MenCut, 'Corte clásico', 3500, 30, [$barber, $colorist]],
            [ServiceCategory::MenCut, 'Degradado con diseño', 4500, 45, [$barber]],
            [ServiceCategory::Beard, 'Perfilado de barba', 2000, 20, [$barber]],
            [ServiceCategory::Beard, 'Afeitado con toalla caliente', 3000, 30, [$barber]],
            [ServiceCategory::Color, 'Tinte completo', 12000, 90, [$colorist]],
            [ServiceCategory::Treatment, 'Hidratación profunda con keratina', 8000, 60, [$colorist]],
        ];

        foreach ($catalog as [$category, $name, $priceCents, $minutes, $performers]) {
            $service = Service::factory()->for($salon)->create([
                'category' => $category,
                'name' => $name,
                'price_cents' => $priceCents,
                'active_minutes' => $minutes,
            ]);

            foreach ($performers as $person) {
                $person->services()->attach($service->id);
            }
        }

        // Color is priced by hair length.
        Service::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->where('category', ServiceCategory::Color)
            ->firstOrFail()
            ->variants()
            ->createMany([
                ['label' => 'Cabello corto', 'price_cents' => 12000, 'extra_minutes' => 0, 'position' => 0],
                ['label' => 'Cabello largo', 'price_cents' => 18000, 'extra_minutes' => 30, 'position' => 1],
            ]);

        $wash = Service::factory()->for($salon)->create([
            'category' => ServiceCategory::MenCut,
            'name' => 'Lavado',
            'price_cents' => 1000,
            'active_minutes' => 10,
            'is_addon' => true,
        ]);
        $barber->services()->attach($wash->id);

        return $salon;
    }

    /**
     * Real photos, through the real image pipeline, so the showcase salon
     * looks like a salon and not like a form.
     *
     * @param  array{Staff, int}  ...$portraits  professional and the id of their portrait
     */
    private function showcasePhotos(Salon $salon, array ...$portraits): void
    {
        app(UpdateSalonBranding::class)->setLogo($salon, DemoImages::monogram($salon->name, (string) $salon->brand_color));
        app(UpdateSalonBranding::class)->setCover($salon, DemoImages::photo(705255));

        foreach ([[3356170, 'Corte a tijera'], [2799605, 'Color'], [1319462, 'Degradado'], [3065171, 'Peinado']] as [$photoId, $caption]) {
            app(ManageSalonPhotos::class)->add($salon, DemoImages::photo($photoId), $caption);
        }

        foreach ($portraits as [$person, $photoId]) {
            app(UpdateStaffPhoto::class)->handle($person, DemoImages::portrait($photoId));
        }
    }
}
