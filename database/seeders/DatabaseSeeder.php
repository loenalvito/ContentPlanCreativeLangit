<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(MasterDataSeeder::class);

        $demoEnabled = app()->environment(['local', 'testing'])
            && filter_var(env('SEED_DEMO_DATA', true), FILTER_VALIDATE_BOOL);

        if ($demoEnabled) {
            $this->call(DemoSeeder::class);
        }
    }
}
