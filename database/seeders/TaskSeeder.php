<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
     public function run(): void
    {
        Task::create([
            'organisation_id' => 1,
            'assignee_id' => 1,
            'title' => 'Test task',
            'description' => 'This is a sample task',
            'status' => 'open',
        ]);
    }
}
