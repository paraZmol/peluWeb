<?php

namespace Database\Seeders;

use App\Actions\Accounting\ManageExpenseCategories;
use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Salon\ManageClientPhotos;
use App\Actions\Salon\ManageSalonPhotos;
use App\Actions\Salon\UpdateSalonBranding;
use App\Actions\Staff\UpdateStaffPhoto;
use App\Data\Booking\NewBooking;
use App\Domain\Accounting\Checkout;
use App\Enums\BookingSource;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\ClientPhotoKind;
use App\Enums\PromotionMechanic;
use App\Enums\PromotionStatus;
use App\Enums\SalonPermission;
use App\Enums\SalonTheme;
use App\Enums\ServiceCategory;
use App\Enums\WaitlistStatus;
use App\Models\Booking;
use App\Models\Promotion;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\DemoImages;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * Known accounts for the Playwright end-to-end tests (tests/e2e).
 * Only ever run against the test database.
 */
class E2eSeeder extends Seeder
{
    public const string PASSWORD = 'password';

    /** Base32 secret of the two-step platform admin (RFC 6238 codes). */
    public const string TWO_FACTOR_SECRET = 'JBSWY3DPEHPK3PXP';

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

        // Runs the platform, without two-step verification yet: their panel has to say what is missing.
        User::factory()->platformAdmin()->create(['name' => 'Paula Plataforma', 'email' => 'plataforma@example.com']);

        // Platform admin with two-step verification on: the secret is known so a test can compute its code.
        User::factory()->platformAdmin()->create([
            'name' => 'Pablo Plataforma',
            'email' => 'plataforma-2fa@example.com',
            'two_factor_secret' => encrypt(self::TWO_FACTOR_SECRET),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
        // Just registered: has not opened the link in the verification email.
        User::factory()->unverified()->create(['name' => 'Nora Nueva', 'email' => 'sin-verificar@example.com']);

        $showcase = $this->seedShowcaseSalon();
        $permissions = $this->seedPermissionsSalon();
        $till = $this->seedTillSalon();
        $files = $this->seedClientFileSalon();
        $waitlist = $this->seedWaitlistSalon();
        $offers = $this->seedOffersSalon();

        // Factories skip what CreateSalon gives every real salon.
        foreach ([$first, $second, $showcase, $till, $files, $permissions, $waitlist, $offers, ...$this->seedReviewSalons(), ...$this->seedPromotionSalons()] as $salon) {
            app(ManageExpenseCategories::class)->createDefaults($salon);
        }
    }

    /**
     * A salon with one team member per permission that opens screens, each
     * with that permission alone, to prove the menu and the server agree.
     */
    private function seedPermissionsSalon(): Salon
    {
        $owner = User::factory()->create(['name' => 'Perla Permisos', 'email' => 'permisos-duena@example.com']);
        $salon = Salon::factory()->ownedBy($owner)->create(['name' => 'Salón Permisos', 'slug' => 'salon-permisos']);

        $alone = [
            'perm-perfil' => ['Pía Perfil', SalonPermission::ManageSalonProfile],
            'perm-servicios' => ['Sol Servicios', SalonPermission::ManageServices],
            'perm-caja' => ['Cata Caja', SalonPermission::ManageCash],
            'perm-reportes' => ['Rubén Reportes', SalonPermission::ViewReports],
            'perm-vip' => ['Vico Vip', SalonPermission::ManageVipClients],
            'perm-agenda' => ['Ági Agenda', SalonPermission::ViewAllSchedules],
        ];

        foreach ($alone as $handle => [$name, $permission]) {
            $user = User::factory()->create(['name' => $name, 'email' => "{$handle}@example.com"]);
            SalonMember::factory()->for($salon)->for($user)->withPermissions($permission)->create();
            Staff::factory()->for($salon)->linkedTo($user)->create(['name' => $name]);
        }

        return $salon;
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
        $salon = Salon::factory()->ownedBy($owner)->create(['name' => 'Salón Caja', 'slug' => 'salon-caja', 'walk_ins_enabled' => true]);
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
     * A salon of its own for the client's file: one person per Playwright
     * project (they share the database), each with a visit they missed, one
     * charged yesterday and one coming tomorrow.
     */
    private function seedClientFileSalon(): Salon
    {
        $owner = User::factory()->create(['name' => 'Fabia Fichas', 'email' => 'ficha-duena@example.com']);
        $salon = Salon::factory()->ownedBy($owner)->create(['name' => 'Salón Fichas', 'slug' => 'salon-fichas']);
        $cut = Service::factory()->for($salon)->create(['name' => 'Corte de ficha', 'price_cents' => 3500, 'active_minutes' => 30]);
        $staff = Staff::factory()->for($salon)->linkedTo($owner)->working([1, 2, 3, 4, 5, 6, 7], '00:00', '23:55')->create(['name' => 'Fabia Fichas']);
        $staff->services()->attach($cut->id);

        $today = $salon->localNow()->startOfDay();
        $status = app(ChangeBookingStatus::class);

        foreach (['Ficha Movil' => 10, 'Ficha Escritorio' => 12] as $name => $hour) {
            $client = SalonClient::factory()->for($salon)->create(['name' => $name, 'phone' => '9876543'.$hour]);
            // Booked an hour ahead, with the clock left at the visit's start.
            $book = function (CarbonImmutable $at, ?string $note = null) use ($salon, $client, $cut, $staff, $owner): Booking {
                CarbonImmutable::setTestNow($at->subHour());
                $booking = app(CreateBooking::class)->handle(new NewBooking(
                    salon: $salon,
                    client: $client,
                    selections: [['service_id' => $cut->id, 'variant_id' => null]],
                    staff: $staff,
                    startsAt: $at->utc(),
                    source: BookingSource::Staff,
                    actor: $owner,
                    note: $note,
                ));
                CarbonImmutable::setTestNow($at);

                return $booking;
            };

            // The past is written as it happened: with the clock on that day.
            $status->markNoShow($book($today->subDays(2)->setTime($hour, 0)), $owner);
            $visit = $book($today->subDay()->setTime($hour, 0), 'Como la última vez, sin máquina');
            $status->start($visit, $owner);
            $status->complete($visit, $owner, new Checkout(parts: ['cash' => 3500]));
            CarbonImmutable::setTestNow();

            $book($today->addDay()->setTime($hour, 0));
            CarbonImmutable::setTestNow();
        }

        $this->seedClientFileTeam($salon, $owner, $cut, $staff);

        // "Mis datos": one person per project with an account of their own
        // (verified address) and a file here, as if they had booked with it,
        // and another in a salon that closed its account since.
        $closed = Salon::factory()->ownedBy($owner)->create(['name' => 'Salón Cerrado', 'slug' => 'salon-cerrado', 'deleted_at' => now()]);

        foreach (['Dato Movil' => 'datos-movil@example.com', 'Dato Escritorio' => 'datos-escritorio@example.com'] as $name => $email) {
            $person = User::factory()->create(['name' => $name, 'email' => $email]);
            SalonClient::factory()->for($salon)->forUser($person)->create();
            SalonClient::factory()->for($closed)->forUser($person)->create();
        }

        return $salon;
    }

    /**
     * The waiting list: a salon whose only professional attends one cut a
     * week, already taken every week, so its only open day never has a free
     * time. One person per Playwright project, each told that a time opened
     * up on the second of those days (the first is theirs to join).
     */
    private function seedWaitlistSalon(): Salon
    {
        $owner = User::factory()->create(['name' => 'Elsa Espera', 'email' => 'espera-duena@example.com']);
        $salon = Salon::factory()->ownedBy($owner)->create(['name' => 'Salón Espera', 'slug' => 'salon-espera']);
        $cut = Service::factory()->for($salon)->create(['name' => 'Corte de espera', 'price_cents' => 3500, 'active_minutes' => 30]);

        // Three days from now: never today, whatever the hour the suite runs at.
        $firstDay = $salon->localNow()->startOfDay()->addDays(3);
        $lastDay = $firstDay->subDays(3)->addDays($salon->max_days_ahead);
        $staff = Staff::factory()->for($salon)->linkedTo($owner)->working([$firstDay->dayOfWeekIso], '10:00', '10:30')->create(['name' => 'Elsa Espera']);
        $staff->services()->attach($cut->id);

        for ($day = $firstDay; $day->lessThanOrEqualTo($lastDay); $day = $day->addWeek()) {
            app(CreateBooking::class)->handle(new NewBooking(
                salon: $salon,
                client: SalonClient::factory()->for($salon)->create(),
                selections: [['service_id' => $cut->id, 'variant_id' => null]],
                staff: $staff,
                startsAt: $day->setTime(10, 0)->utc(),
                source: BookingSource::Staff,
                actor: $owner,
            ));
        }

        foreach (['Espera Movil' => 'espera-movil@example.com', 'Espera Escritorio' => 'espera-escritorio@example.com'] as $name => $email) {
            $person = User::factory()->create(['name' => $name, 'email' => $email]);
            $client = SalonClient::factory()->for($salon)->forUser($person)->create();

            $turn = new WaitlistEntry;
            $turn->salon_id = $salon->id;
            $turn->salon_client_id = $client->id;
            $turn->date = $firstDay->addWeek();
            $turn->selections = [['service_id' => $cut->id, 'variant_id' => null]];
            $turn->status = WaitlistStatus::Offered;
            $turn->offers_count = 1;
            $turn->offered_at = CarbonImmutable::now();
            $turn->offer_expires_at = CarbonImmutable::now()->addMinutes(WaitlistEntry::OFFER_MINUTES);
            $turn->save();
        }

        return $salon;
    }

    /**
     * Reviews: a salon per Playwright project (each one switches its own
     * between private and public), with its owner and a person who has an
     * account and a visit charged yesterday, ready to be reviewed.
     *
     * @return list<Salon>
     */
    private function seedReviewSalons(): array
    {
        $salons = [];
        $status = app(ChangeBookingStatus::class);

        foreach (['movil' => ['Rene Movil', 'Milan Movil'], 'escritorio' => ['Rene Escritorio', 'Eli Escritorio']] as $project => [$ownerName, $clientName]) {
            $owner = User::factory()->create(['name' => $ownerName, 'email' => "resenas-duena-{$project}@example.com"]);
            $salon = Salon::factory()->ownedBy($owner)->create(['name' => 'Salón Reseñas '.ucfirst($project), 'slug' => "salon-resenas-{$project}"]);
            $cut = Service::factory()->for($salon)->create(['name' => 'Corte de reseña', 'price_cents' => 3500, 'active_minutes' => 30]);
            $staff = Staff::factory()->for($salon)->linkedTo($owner)->working([1, 2, 3, 4, 5, 6, 7], '00:00', '23:55')->create(['name' => $ownerName]);
            $staff->services()->attach($cut->id);
            $person = User::factory()->create(['name' => $clientName, 'email' => "resenas-{$project}@example.com"]);
            $client = SalonClient::factory()->for($salon)->forUser($person)->create();

            // The past is written as it happened: with the clock on that day.
            $at = $salon->localNow()->startOfDay()->subDay()->setTime(11, 0);
            CarbonImmutable::setTestNow($at->subHour());
            $visit = app(CreateBooking::class)->handle(new NewBooking(
                salon: $salon,
                client: $client,
                selections: [['service_id' => $cut->id, 'variant_id' => null]],
                staff: $staff,
                startsAt: $at->utc(),
                source: BookingSource::Staff,
                actor: $owner,
            ));
            CarbonImmutable::setTestNow($at);
            $status->start($visit, $owner);
            $status->complete($visit, $owner, new Checkout(parts: ['cash' => 3500]));
            CarbonImmutable::setTestNow();

            $salons[] = $salon;
        }

        return $salons;
    }

    /**
     * Promotions: a salon per Playwright project (each publishes its own,
     * and a salon runs three at most), with a brand color of its own (a
     * vivid one, a dark one), a deposit, two services and a short add-on
     * with one price, the kind that can be given away.
     *
     * @return list<Salon>
     */
    private function seedPromotionSalons(): array
    {
        $salons = [];

        foreach (['movil' => ['Prisca Movil', '#E11D48'], 'escritorio' => ['Prisca Escritorio', '#1F2937']] as $project => [$ownerName, $brandColor]) {
            $owner = User::factory()->create(['name' => $ownerName, 'email' => "promos-duena-{$project}@example.com"]);
            $salon = Salon::factory()->ownedBy($owner)->create([
                'name' => 'Salón Promos '.ucfirst($project),
                'slug' => "salon-promos-{$project}",
                'brand_color' => $brandColor,
                'deposit_percent' => 30,
                'payment_instructions' => 'Yape al 987 654 321 a nombre de Salón Promos.',
            ]);
            $staff = Staff::factory()->for($salon)->linkedTo($owner)->working([1, 2, 3, 4, 5, 6, 7], '00:00', '23:55')->create(['name' => $ownerName]);

            $catalog = [
                ['Corte de promo', 3500, 30, false],
                ['Barba de promo', 2000, 20, false],
                ['Lavado de promo', 1000, 10, true],
            ];

            foreach ($catalog as $position => [$name, $priceCents, $minutes, $isAddon]) {
                $service = Service::factory()->for($salon)->create([
                    'name' => $name,
                    'price_cents' => $priceCents,
                    'active_minutes' => $minutes,
                    'is_addon' => $isAddon,
                    'position' => $position,
                ]);
                $staff->services()->attach($service->id);
            }

            $salons[] = $salon;
        }

        return $salons;
    }

    /**
     * What a client sees of the promotions: a salon with two of them running
     * and one person per Playwright project to book with. A discount that
     * holds only from 9 to 18, so every day has times with and without it,
     * and a gift earned by another service: each promotion on its own
     * service, so which one a booking gets never depends on a tie.
     */
    private function seedOffersSalon(): Salon
    {
        $owner = User::factory()->create(['name' => 'Olivia Ofertas', 'email' => 'ofertas-duena@example.com']);
        $salon = Salon::factory()->ownedBy($owner)->create([
            'name' => 'Salón Ofertas',
            'slug' => 'salon-ofertas',
            'theme' => SalonTheme::Vibrante,
            'brand_color' => '#E11D48',
            'deposit_percent' => 30,
            'payment_instructions' => 'Yape al 987 654 321 a nombre de Salón Ofertas.',
        ]);
        $staff = Staff::factory()->for($salon)->linkedTo($owner)->working([1, 2, 3, 4, 5, 6, 7], '07:00', '22:00')->create(['name' => 'Olivia Ofertas']);
        $this->showcasePhotos($salon, [$staff, 3992875]);

        $services = [];

        foreach ([['Corte de oferta', 4000, 30, false], ['Barba de oferta', 2000, 20, false], ['Lavado de oferta', 1000, 10, true]] as $position => [$name, $priceCents, $minutes, $isAddon]) {
            $services[] = $service = Service::factory()->for($salon)->create([
                'name' => $name,
                'price_cents' => $priceCents,
                'active_minutes' => $minutes,
                'is_addon' => $isAddon,
                'position' => $position,
            ]);
            $staff->services()->attach($service->id);
        }

        [$cut, $beard, $wash] = $services;
        $today = $salon->localNow()->startOfDay();
        $running = [
            'salon_id' => $salon->id,
            'status' => PromotionStatus::Published,
            'starts_on' => $today->subDay()->toDateString(),
            'ends_on' => $today->addDays(20)->toDateString(),
            'published_at' => now(),
        ];

        $discount = new Promotion;
        $discount->forceFill([
            ...$running,
            'mechanic' => PromotionMechanic::Discount,
            'card' => 'quiet_hours',
            'name' => '25 % menos en Corte de oferta',
            'percent' => 25,
            'from_minute' => 9 * 60,
            'to_minute' => 18 * 60,
            'share_code' => 'ofertacorte',
        ])->save();
        $discount->services()->sync([$cut->id]);

        $gift = new Promotion;
        $gift->forceFill([
            ...$running,
            'mechanic' => PromotionMechanic::Gift,
            'card' => 'season',
            'name' => 'Lavado de oferta gratis con tu Barba de oferta',
            'overline' => 'Regreso a clases',
            'gift_service_id' => $wash->id,
            'share_code' => 'ofertaregalo',
        ])->save();
        $gift->services()->sync([$beard->id]);

        foreach (['Oferta Movil' => 'ofertas-movil@example.com', 'Oferta Escritorio' => 'ofertas-escritorio@example.com'] as $name => $email) {
            User::factory()->create(['name' => $name, 'email' => $email]);
        }

        return $salon;
    }

    /**
     * The rest of the team of "Salón Fichas" and the people they attend, to
     * try who may open a file and who sees health notes and pictures:
     * a professional (paco-ficha@) and a receptionist who sees every agenda
     * but attends no one (rita-ficha@).
     */
    private function seedClientFileTeam(Salon $salon, User $owner, Service $cut, Staff $fabia): void
    {
        $paco = User::factory()->create(['name' => 'Paco Profesional', 'email' => 'paco-ficha@example.com']);
        SalonMember::factory()->for($salon)->for($paco)->create();
        $pacoStaff = Staff::factory()->for($salon)->linkedTo($paco)->working([1, 2, 3, 4, 5, 6, 7], '00:00', '23:55')->create(['name' => 'Paco Profesional']);
        $pacoStaff->services()->attach($cut->id);
        $rita = User::factory()->create(['name' => 'Rita Recepcion', 'email' => 'rita-ficha@example.com']);
        SalonMember::factory()->for($salon)->for($rita)->withPermissions(SalonPermission::ViewAllSchedules)->create();

        $today = $salon->localNow()->startOfDay();
        $status = app(ChangeBookingStatus::class);
        $visit = function (SalonClient $client, Staff $with, CarbonImmutable $at, bool $done) use ($salon, $cut, $owner, $status): void {
            CarbonImmutable::setTestNow($at->subHour());
            $booking = app(CreateBooking::class)->handle(new NewBooking(
                salon: $salon,
                client: $client,
                selections: [['service_id' => $cut->id, 'variant_id' => null]],
                staff: $with,
                startsAt: $at->utc(),
                source: BookingSource::Staff,
                actor: $owner,
            ));
            CarbonImmutable::setTestNow($at);

            if ($done) {
                $status->start($booking, $owner);
                $status->complete($booking, $owner, new Checkout(parts: ['cash' => 3500]));
            }

            CarbonImmutable::setTestNow();
        };

        // Attended by Paco, with health notes and a picture already kept.
        $attended = SalonClient::factory()->for($salon)->create(['name' => 'Atendida Paco', 'phone' => '987654400']);
        $visit($attended, $pacoStaff, $today->subDay()->setTime(14, 0), true);
        $visit($attended, $pacoStaff, $today->addDay()->setTime(14, 0), false);
        $attended->care_notes = 'Alergia al amoníaco';
        $attended->care_consent_at = now();
        $attended->care_consent_by = $owner->id;
        $attended->save();
        $source = database_path('seeders/images/pexels-3993312.jpg');
        app(ManageClientPhotos::class)->add($attended, ClientPhotoKind::Before, new UploadedFile($source, 'antes.jpg', 'image/jpeg', null, true), $owner);

        // One per project for the allergy flow done by a professional.
        foreach (['Flujo Movil' => 15, 'Flujo Escritorio' => 16] as $name => $hour) {
            $flow = SalonClient::factory()->for($salon)->create(['name' => $name, 'phone' => '9876545'.$hour]);
            $visit($flow, $pacoStaff, $today->subDay()->setTime($hour, 0), true);
        }

        // Only Fabia attends her: Paco must not open the file.
        $other = SalonClient::factory()->for($salon)->create(['name' => 'Ajena Fabia', 'phone' => '987654401']);
        $visit($other, $fabia, $today->subDay()->setTime(17, 0), true);

        // No phone, no visits, nothing booked.
        SalonClient::factory()->for($salon)->create(['name' => 'Sin Datos', 'phone' => null]);

        // More visits than the file lists.
        $long = SalonClient::factory()->for($salon)->create(['name' => 'Larga Historia', 'phone' => '987654402']);
        foreach (range(3, 37) as $daysAgo) {
            $visit($long, $fabia, $today->subDays($daysAgo)->setTime(18, 0), true);
        }
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
