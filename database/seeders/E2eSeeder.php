<?php

namespace Database\Seeders;

use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Known accounts for the Playwright end-to-end tests (tests/e2e).
 * Only ever run against the test database.
 */
class E2eSeeder extends Seeder
{
    public const string PASSWORD = 'password';

    public function run(): void
    {
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
    }
}
