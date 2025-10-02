<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Organisation;

class OrganisationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         Organisation::create([
        'name' => 'Example Corp',
        'address' => '123 Main Street',
        'phone' => '123456789',
        'email' => 'info@example.com',
    ]);
    }
}
