<?php

namespace Database\Seeders;

use App\Models\Salon;
use App\Models\SalonMember;
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
        Salon::factory()->ownedBy($multi)->create(['name' => 'Salón Uno', 'slug' => 'salon-uno']);
        $second = Salon::factory()->ownedBy($multi)->create(['name' => 'Salón Dos Ñandú', 'slug' => 'salon-dos']);

        $staff = User::factory()->create(['name' => 'Pedro Personal', 'email' => 'personal@example.com']);
        SalonMember::factory()->for($second)->for($staff)->create();
    }
}
