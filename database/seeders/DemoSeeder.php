<?php

namespace Database\Seeders;

use App\Actions\Salon\CreateSalon;
use App\Actions\Salon\ManageSalonPhotos;
use App\Actions\Salon\UpdateSalonBranding;
use App\Actions\Salon\UpdateSalonDetails;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\SalonPermission;
use App\Enums\SalonRole;
use App\Models\Salon;
use App\Models\SalonInvitation;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\User;
use App\Support\CurrentSalon;
use Database\Seeders\Support\DemoImages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use LogicException;

/**
 * Realistic demo data for trying the app locally (password: "password").
 * Goes through the real Actions so it follows the same rules as the UI.
 * Idempotent: running it again does nothing if the demo owner exists.
 *
 *   C:\php84\php.exe artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public const string PASSWORD = 'password';

    /**
     * @var list<array{owner: array{name: string, email: string}, salon: array<string, mixed>, color: string, prices: array<string, int>, photos: array{cover: int, gallery: list<array{int, string}>}}>
     */
    private const array SALONS = [
        [
            'owner' => ['name' => 'Luis Quispe', 'email' => 'barberia@peluweb.test'],
            'salon' => [
                'name' => 'Barbería Don Lucho', 'type' => BusinessType::Barbershop, 'clientele' => Clientele::Men,
                'phone' => '987654321', 'instagram' => 'barberiadonlucho',
                'description' => "Cortes clásicos y modernos desde 1998. Fades, barba con toalla caliente y buena conversa.\nAtendemos con cita o por orden de llegada.",
                'address' => 'Av. José Larco 345', 'district' => 'Miraflores', 'city' => 'Lima',
                'latitude' => -12.1211, 'longitude' => -77.0297,
            ],
            'color' => '#1f1b18',
            'prices' => ['Degradado medio' => 3500, 'Degradado a piel' => 4000, 'Perfilado de barba' => 2000, 'Combo corte + barba' => 5000],
            'photos' => ['cover' => 1813272, 'gallery' => [[1836983, 'Degradado con diseño'], [2076932, 'Corte clásico a máquina'], [4625648, 'Corte con tijera'], [3998417, 'Perfilado de barba'], [897265, 'Barba con navaja'], [7518728, 'Nuestro local']]],
        ],
        [
            'owner' => ['name' => 'Valeria Rojas', 'email' => 'salon@peluweb.test'],
            'salon' => [
                'name' => 'Studio Bella', 'type' => BusinessType::BeautySalon, 'clientele' => Clientele::Women,
                'phone' => '912345678', 'instagram' => 'studiobella.pe',
                'description' => 'Especialistas en color, balayage y tratamientos de keratina. Productos profesionales y asesoría de imagen sin costo.',
                'address' => 'Calle Los Laureles 120', 'district' => 'San Isidro', 'city' => 'Lima',
                'latitude' => -12.0970, 'longitude' => -77.0365,
            ],
            'color' => '#c2185b',
            'prices' => ['Balayage' => 42000, 'Tinte de raíz' => 9500, 'Corte en capas' => 7000],
            'photos' => ['cover' => 3065171, 'gallery' => [[3993311, 'Color y brillo'], [2799605, 'Tinte completo'], [6503329, 'Alisado'], [7440133, 'Secado y peinado'], [3993133, 'Nuestro tocador'], [705255, 'Nuestro salón']]],
        ],
        [
            'owner' => ['name' => 'Jorge Huamán', 'email' => 'unisex@peluweb.test'],
            'salon' => [
                'name' => 'Corte & Color Surco', 'type' => BusinessType::Unisex, 'clientele' => Clientele::Everyone,
                'phone' => '955112233', 'instagram' => null,
                'description' => 'Peluquería para toda la familia: cortes, color y peinados para eventos.',
                'address' => 'Av. Caminos del Inca 2300', 'district' => 'Santiago de Surco', 'city' => 'Lima',
                'latitude' => -12.1390, 'longitude' => -76.9930,
            ],
            'color' => '#0f766e',
            'prices' => [],
            'photos' => ['cover' => 705255, 'gallery' => [[7518731, 'Corte de caballero'], [3065170, 'Recogido'], [3356170, 'Corte con tijera'], [1319462, 'Degradado'], [3993312, 'Color'], [2076932, 'Corte a máquina']]],
        ],
        [
            'owner' => ['name' => 'Camila Torres', 'email' => 'unas@peluweb.test'],
            'salon' => [
                'name' => 'Nails Spa Barranco', 'type' => BusinessType::Nails, 'clientele' => Clientele::Everyone,
                'phone' => '944556677', 'instagram' => 'nailsspabarranco',
                'description' => 'Manicure, pedicure spa, gel y nail art en un ambiente tranquilo frente al mar.',
                'address' => 'Jr. Domeyer 210', 'district' => 'Barranco', 'city' => 'Lima',
                'latitude' => -12.1490, 'longitude' => -77.0220,
            ],
            'color' => '#6d28d9',
            'prices' => ['Manicure semipermanente' => 4500, 'Pedicure spa' => 7000],
            'photos' => ['cover' => 3422099, 'gallery' => [[22668324, 'Esmaltado en gel'], [16363470, 'Manicura brillante'], [14267564, 'Limado y forma'], [17056221, 'Pedicura spa'], [19239100, 'Manicura clásica'], [7990099, 'Cuidado de cutículas']]],
        ],
    ];

    public function __construct(
        private readonly CreateSalon $createSalon,
        private readonly UpdateSalonDetails $details,
        private readonly UpdateSalonBranding $branding,
        private readonly ManageSalonPhotos $photos,
        private readonly CurrentSalon $currentSalon,
    ) {}

    public function run(): void
    {
        // Its accounts share a public password: never on a real server.
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('DemoSeeder only runs in local and testing.');
        }

        if (User::where('email', self::SALONS[0]['owner']['email'])->exists()) {
            $this->command->info('Los datos de ejemplo ya existen. Nada que hacer.');

            return;
        }

        $this->user('Admin Plataforma', 'admin@peluweb.test')->forceFill(['is_platform_admin' => true])->save();
        $this->user('Ana Cliente', 'cliente@peluweb.test', phone: '+51999111222');

        $salons = array_map(fn (array $demo) => $this->salon($demo), self::SALONS);
        $this->team($salons[0]);
        $this->call(DemoScheduleSeeder::class);

        $this->currentSalon->set(null);
        $this->printAccounts();
    }

    /**
     * @param  array{owner: array{name: string, email: string}, salon: array<string, mixed>, color: string, prices: array<string, int>, photos: array{cover: int, gallery: list<array{int, string}>}}  $demo
     */
    private function salon(array $demo): Salon
    {
        $data = $demo['salon'];
        $owner = $this->user($demo['owner']['name'], $demo['owner']['email']);

        $salon = $this->createSalon->handle($owner, $data['name'], $data['type'], $data['clientele'], $data['phone']);
        $this->currentSalon->set($salon);

        $this->details->handle($salon, collect($data)->only([
            'description', 'instagram', 'address', 'district', 'city', 'latitude', 'longitude',
        ])->all());
        $this->branding->setColor($salon, $demo['color']);
        $this->branding->setLogo($salon, DemoImages::monogram($data['name'], $demo['color']));
        $this->branding->setCover($salon, DemoImages::photo($demo['photos']['cover']));

        foreach ($demo['photos']['gallery'] as [$photoId, $caption]) {
            $this->photos->add($salon, DemoImages::photo($photoId), $caption);
        }

        foreach ($demo['prices'] as $name => $cents) {
            Service::query()->where('name', $name)->first()?->forceFill(['price_cents' => $cents])->save();
        }

        return $salon;
    }

    /**
     * A barber with permission to manage services, a staff member without
     * account permissions, a pending invitation, and an owner of two salons.
     */
    private function team(Salon $barbershop): void
    {
        $this->member($barbershop, $this->user('Pedro Barbero', 'personal@peluweb.test'), [SalonPermission::ManageServices]);
        $this->member($barbershop, $this->user('Kevin Aprendiz', 'aprendiz@peluweb.test'), []);

        $this->currentSalon->set($barbershop);
        $invitation = new SalonInvitation;
        $invitation->forceFill([
            'salon_id' => $barbershop->id,
            'email' => 'nuevo.barbero@peluweb.test',
            'role' => SalonRole::Staff,
            'permissions' => [],
            'token_hash' => SalonInvitation::hashToken(Str::random(48)),
            'expires_at' => now()->addDays(SalonInvitation::VALID_DAYS),
        ])->save();

        // Valeria also co-owns the barbershop: shows the salon switcher.
        $valeria = User::where('email', 'salon@peluweb.test')->firstOrFail();
        $this->member($barbershop, $valeria, [], SalonRole::Owner);
    }

    /**
     * @param  list<SalonPermission>  $permissions
     */
    private function member(Salon $salon, User $user, array $permissions, SalonRole $role = SalonRole::Staff): void
    {
        $member = new SalonMember;
        $member->salon_id = $salon->id;
        $member->user_id = $user->id;
        $member->role = $role;
        $member->permissions = collect($permissions);
        $member->save();
    }

    private function user(string $name, string $email, ?string $phone = null): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => self::PASSWORD,
        ]);
    }

    private function printAccounts(): void
    {
        $this->command->table(['Cuenta', 'Correo', 'Contraseña'], [
            ['Super admin (/plataforma; activa 2FA en Ajustes)', 'admin@peluweb.test', self::PASSWORD],
            ['Dueño · Barbería Don Lucho', 'barberia@peluweb.test', self::PASSWORD],
            ['Dueña · Studio Bella (y co-dueña de la barbería)', 'salon@peluweb.test', self::PASSWORD],
            ['Dueño · Corte & Color Surco', 'unisex@peluweb.test', self::PASSWORD],
            ['Dueña · Nails Spa Barranco', 'unas@peluweb.test', self::PASSWORD],
            ['Barbero con permiso de servicios (ve "Mi horario")', 'personal@peluweb.test', self::PASSWORD],
            ['Personal sin permisos extra', 'aprendiz@peluweb.test', self::PASSWORD],
            ['Cliente sin salón', 'cliente@peluweb.test', self::PASSWORD],
        ]);
        $this->command->info('Páginas públicas: /s/barberia-don-lucho, /s/studio-bella, /s/corte-color-surco, /s/nails-spa-barranco');
    }
}
