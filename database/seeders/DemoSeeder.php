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
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

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
     * @var list<array{owner: array{name: string, email: string}, salon: array<string, mixed>, color: string, prices: array<string, int>}>
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
     * @param  array{owner: array{name: string, email: string}, salon: array<string, mixed>, color: string, prices: array<string, int>}  $demo
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
        $this->branding->setLogo($salon, $this->image('logo.png', 512, 512, $demo['color']));
        $this->branding->setCover($salon, $this->image('portada.png', 1600, 900, $demo['color']));

        foreach (range(1, 4) as $number) {
            $this->photos->add($salon, $this->image("trabajo-{$number}.png", 1200, 1200, $demo['color'], $number), "Trabajo {$number}");
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

    /**
     * A simple branded placeholder image (diagonal stripes in the salon
     * color) so pages look like a real salon without bundling photos.
     */
    private function image(string $name, int $width, int $height, string $color, int $variant = 0): UploadedFile
    {
        $image = (new ImageManager(new Driver))->create($width, $height)->fill('#f4f0eb');
        $stripe = (int) ($width / 6);

        for ($x = -$height + ($variant * 37) % $stripe; $x < $width; $x += $stripe * 2) {
            $image->drawPolygon(function ($polygon) use ($x, $height, $stripe, $color): void {
                $polygon->point($x, $height);
                $polygon->point($x + $stripe, $height);
                $polygon->point($x + $stripe + $height, 0);
                $polygon->point($x + $height, 0);
                $polygon->background($color);
            });
        }

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'peluweb-demo-'.Str::random(12).'.png';
        $image->save($path);

        return new UploadedFile($path, $name, 'image/png', null, true);
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
