<?php

use App\Exceptions\AiUnavailableException;
use App\Models\Expense;
use App\Models\Group;
use App\Models\User;
use App\Services\ExpenseAiService;
use Illuminate\Http\UploadedFile;

// The real ExpenseAiService calls the Claude API; these tests replace it with a mock
// so they check the app's handling of AI results without network access or cost.

function aiGroup(): array
{
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    $group = Group::create(['created_by' => $alice->id, 'name' => 'Flat']);
    $group->members()->attach([$alice->id, $bob->id]);

    return [$group, $alice, $bob];
}

test('scanning a receipt pre-fills the expense form without saving', function () {
    [$group, $alice] = aiGroup();

    $this->mock(ExpenseAiService::class)
        ->shouldReceive('scanReceipt')->once()
        ->andReturn(['description' => 'Tesco groceries', 'amount' => 42.5, 'category' => 'Groceries']);

    $this->actingAs($alice)
        ->post(route('expenses.ai.receipt', $group), ['receipt' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf')])
        ->assertRedirect(route('expenses.create', $group))
        ->assertSessionHasInput('description', 'Tesco groceries')
        ->assertSessionHasInput('amount', 42.5)
        ->assertSessionHasInput('category', 'Groceries');

    expect(Expense::count())->toBe(0);
});

test('an unreadable receipt shows an error', function () {
    [$group, $alice] = aiGroup();

    $this->mock(ExpenseAiService::class)
        ->shouldReceive('scanReceipt')->andThrow(new AiUnavailableException('Could not read it.'));

    $this->actingAs($alice)
        ->from(route('expenses.create', $group))
        ->post(route('expenses.ai.receipt', $group), ['receipt' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf')])
        ->assertRedirect(route('expenses.create', $group))
        ->assertSessionHasErrors(['receipt' => 'Could not read it.']);
});

test('natural-language entry pre-fills the expense form', function () {
    [$group, $alice, $bob] = aiGroup();

    $this->mock(ExpenseAiService::class)
        ->shouldReceive('parseText')->once()
        ->andReturn([
            'description' => 'Pizza',
            'amount' => 30.0,
            'paid_by' => $bob->id,
            'participant_ids' => [$alice->id, $bob->id],
            'category' => 'Food & Drink',
        ]);

    $this->actingAs($alice)
        ->post(route('expenses.ai.text', $group), ['text' => 'Bob paid 30 for pizza with me'])
        ->assertRedirect(route('expenses.create', $group))
        ->assertSessionHasInput('paid_by', $bob->id)
        ->assertSessionHasInput('category', 'Food & Drink');
});

test('non-members cannot use the AI helpers', function () {
    [$group] = aiGroup();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->post(route('expenses.ai.text', $group), ['text' => 'anything'])
        ->assertForbidden();
});

test('an expense saved with no category is categorised automatically', function () {
    [$group, $alice, $bob] = aiGroup();

    $ai = $this->mock(ExpenseAiService::class);
    $ai->shouldReceive('enabled')->andReturn(true);
    $ai->shouldReceive('categorise')->with('Electricity bill')->once()->andReturn('Utilities');

    $this->actingAs($alice)->post(route('expenses.store', $group), [
        'description' => 'Electricity bill',
        'amount' => 80,
        'paid_by' => $alice->id,
        'participant_ids' => [$alice->id, $bob->id],
    ])->assertRedirect();

    expect(Expense::first()->category)->toBe('Utilities');
});

test('an expense still saves when categorisation fails', function () {
    [$group, $alice, $bob] = aiGroup();

    $ai = $this->mock(ExpenseAiService::class);
    $ai->shouldReceive('enabled')->andReturn(true);
    $ai->shouldReceive('categorise')->andThrow(new AiUnavailableException('down'));

    $this->actingAs($alice)->post(route('expenses.store', $group), [
        'description' => 'Taxi',
        'amount' => 20,
        'paid_by' => $alice->id,
        'participant_ids' => [$alice->id, $bob->id],
    ])->assertRedirect();

    expect(Expense::first()->category)->toBeNull();
});

test('a chosen category skips the AI', function () {
    [$group, $alice, $bob] = aiGroup();

    $this->mock(ExpenseAiService::class)->shouldNotReceive('categorise');

    $this->actingAs($alice)->post(route('expenses.store', $group), [
        'description' => 'Cinema',
        'amount' => 24,
        'paid_by' => $alice->id,
        'participant_ids' => [$alice->id, $bob->id],
        'category' => 'Entertainment',
    ])->assertRedirect();

    expect(Expense::first()->category)->toBe('Entertainment');
});

test('insights page shows monthly totals and a generated summary', function () {
    [$group, $alice] = aiGroup();

    $group->expenses()->create(['paid_by' => $alice->id, 'description' => 'Rent', 'amount' => 1000, 'category' => 'Rent']);
    $group->expenses()->create(['paid_by' => $alice->id, 'description' => 'Food', 'amount' => 50, 'category' => 'Groceries']);

    $ai = $this->mock(ExpenseAiService::class);
    $ai->shouldReceive('enabled')->andReturn(true);
    $ai->shouldReceive('insights')->once()->withArgs(fn ($name, $stats) => $stats['total'] === 1050.0
        && $stats['by_category'] === ['Rent' => 1000.0, 'Groceries' => 50.0])
        ->andReturn(['headline' => 'Rent dominated the month.', 'highlights' => ['Alice covered everything.'], 'tip' => 'Rotate who pays.']);

    $this->actingAs($alice)
        ->post(route('groups.insights.generate', $group))
        ->assertRedirect();

    $this->actingAs($alice)
        ->get(route('groups.insights', $group))
        ->assertOk()
        ->assertSee('$1,050.00')
        ->assertSee('Rent dominated the month.')
        ->assertSee('Rotate who pays.');
});
