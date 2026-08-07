<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Nguyen', 'email' => 'alice@example.com']);
        $bob = User::factory()->create(['name' => 'Bob Martinez', 'email' => 'bob@example.com']);
        $carol = User::factory()->create(['name' => 'Carol Singh', 'email' => 'carol@example.com']);
        $dave = User::factory()->create(['name' => 'Dave Okafor', 'email' => 'dave@example.com']);

        $group = Group::create([
            'created_by' => $alice->id,
            'name' => 'Roommates',
        ]);
        $group->members()->attach([$alice->id, $bob->id, $carol->id, $dave->id]);

        // Alice pays for dinner, split evenly three ways (Bob and Carol only).
        $dinner = $group->expenses()->create([
            'paid_by' => $alice->id,
            'description' => 'Dinner out',
            'amount' => 90.00,
        ]);
        $dinner->shares()->createMany([
            ['user_id' => $alice->id, 'amount' => 30.00],
            ['user_id' => $bob->id, 'amount' => 30.00],
            ['user_id' => $carol->id, 'amount' => 30.00],
        ]);

        // Bob pays for groceries, split evenly across all four.
        $groceries = $group->expenses()->create([
            'paid_by' => $bob->id,
            'description' => 'Groceries',
            'amount' => 60.00,
        ]);
        $groceries->shares()->createMany([
            ['user_id' => $alice->id, 'amount' => 15.00],
            ['user_id' => $bob->id, 'amount' => 15.00],
            ['user_id' => $carol->id, 'amount' => 15.00],
            ['user_id' => $dave->id, 'amount' => 15.00],
        ]);

        // Dave pays for a $100 utility bill split three ways -- doesn't divide evenly,
        // demonstrating the same whole-cent remainder handling as BalanceService.
        $utilities = $group->expenses()->create([
            'paid_by' => $dave->id,
            'description' => 'Utility bill',
            'amount' => 100.00,
        ]);
        $utilities->shares()->createMany([
            ['user_id' => $bob->id, 'amount' => 33.34],
            ['user_id' => $carol->id, 'amount' => 33.33],
            ['user_id' => $dave->id, 'amount' => 33.33],
        ]);

        // Carol has already paid Alice back for part of the dinner.
        $group->settlements()->create([
            'from_user_id' => $carol->id,
            'to_user_id' => $alice->id,
            'amount' => 15.00,
            'settled_at' => now()->subDay(),
        ]);

        $this->command->info('Demo users — alice/bob/carol/dave@example.com (password: password)');
    }
}
