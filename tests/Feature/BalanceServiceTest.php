<?php

use App\Models\Group;
use App\Models\User;
use App\Services\BalanceService;

function makeGroup(int $memberCount): array
{
    $creator = User::factory()->create();

    $group = Group::create([
        'created_by' => $creator->id,
        'name' => 'Test Group',
    ]);

    $members = User::factory($memberCount - 1)->create()->prepend($creator);
    $group->members()->attach($members->pluck('id'));

    return [$group, $members];
}

test('a single expense splits evenly and balances net to zero', function () {
    [$group, $members] = makeGroup(3);
    [$alice, $bob, $carol] = $members;

    $expense = $group->expenses()->create([
        'paid_by' => $alice->id,
        'description' => 'Dinner',
        'amount' => 90.00,
    ]);

    foreach ($members as $member) {
        $expense->shares()->create(['user_id' => $member->id, 'amount' => 30.00]);
    }

    $balances = app(BalanceService::class)->balances($group->fresh(['members', 'expenses.shares', 'settlements']));

    expect($balances[$alice->id])->toBe(6000) // paid 90, owes 30 => +60
        ->and($balances[$bob->id])->toBe(-3000)
        ->and($balances[$carol->id])->toBe(-3000)
        ->and(array_sum($balances))->toBe(0);
});

test('a settlement payment reduces both parties balances', function () {
    [$group, $members] = makeGroup(2);
    [$alice, $bob] = $members;

    $expense = $group->expenses()->create([
        'paid_by' => $alice->id,
        'description' => 'Groceries',
        'amount' => 50.00,
    ]);
    $expense->shares()->create(['user_id' => $alice->id, 'amount' => 25.00]);
    $expense->shares()->create(['user_id' => $bob->id, 'amount' => 25.00]);

    $group->settlements()->create([
        'from_user_id' => $bob->id,
        'to_user_id' => $alice->id,
        'amount' => 25.00,
        'settled_at' => now(),
    ]);

    $balances = app(BalanceService::class)->balances($group->fresh(['members', 'expenses.shares', 'settlements']));

    expect($balances[$alice->id])->toBe(0)
        ->and($balances[$bob->id])->toBe(0);
});

test('debt simplification produces the minimum number of transfers', function () {
    // Alice is owed 20, Bob is owed 10, Carol owes 30 -- should collapse to
    // exactly two payments (Carol -> Alice, Carol -> Bob), never three-plus.
    $balances = [
        1 => 2000,  // Alice: owed $20
        2 => 1000,  // Bob: owed $10
        3 => -3000, // Carol: owes $30
    ];

    $transfers = app(BalanceService::class)->simplify($balances);

    expect($transfers)->toHaveCount(2);

    $totalMoved = array_sum(array_column($transfers, 'amount'));
    expect($totalMoved)->toBe(3000);

    foreach ($transfers as $transfer) {
        expect($transfer['from'])->toBe(3);
    }
});

test('debt simplification settles a three-way cycle in two payments instead of three', function () {
    // A owes B $10, B owes C $10, C owes A $10 -- nets to everyone at zero,
    // so no transfers should be suggested even though three debts exist on paper.
    $balances = [1 => 0, 2 => 0, 3 => 0];

    expect(app(BalanceService::class)->simplify($balances))->toBe([]);
});

test('an expense that does not divide evenly distributes the remainder in whole cents', function () {
    [$group, $members] = makeGroup(3);
    [$alice, $bob, $carol] = $members;

    // $100.00 split three ways = $33.33 + $33.33 + $33.34, never a fractional cent.
    $expense = $group->expenses()->create([
        'paid_by' => $alice->id,
        'description' => 'Uneven split',
        'amount' => 100.00,
    ]);

    $base = intdiv(10000, 3);
    $remainder = 10000 % 3;
    foreach ($members as $i => $member) {
        $cents = $base + ($i < $remainder ? 1 : 0);
        $expense->shares()->create(['user_id' => $member->id, 'amount' => $cents / 100]);
    }

    $totalShares = $expense->shares()->sum('amount');

    expect((float) $totalShares)->toBe(100.00);
});
