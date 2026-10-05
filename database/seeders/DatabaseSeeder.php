<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Local development data. Model events stay on: salon scoping and the
     * real Actions rely on them.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
