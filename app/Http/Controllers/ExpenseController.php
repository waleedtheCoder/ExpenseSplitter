<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function create(Group $group): View
    {
        $this->authorize('addExpense', $group);

        $group->load('members');

        return view('expenses.create', compact('group'));
    }

    /** Creates the expense and splits it evenly (in cents) across the chosen participants. */
    public function store(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('addExpense', $group);

        $memberIds = $group->members->pluck('id');

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_by' => ['required', 'integer', Rule::in($memberIds)],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => [Rule::in($memberIds)],
        ]);

        DB::transaction(function () use ($group, $data) {
            $expense = $group->expenses()->create([
                'paid_by' => $data['paid_by'],
                'description' => $data['description'],
                'amount' => $data['amount'],
            ]);

            $totalCents = (int) round($data['amount'] * 100);
            $participantIds = array_values(array_unique($data['participant_ids']));
            $count = count($participantIds);

            $base = intdiv($totalCents, $count);
            $remainder = $totalCents % $count;

            foreach ($participantIds as $i => $userId) {
                $cents = $base + ($i < $remainder ? 1 : 0);

                $expense->shares()->create([
                    'user_id' => $userId,
                    'amount' => $cents / 100,
                ]);
            }
        });

        return redirect()->route('groups.show', $group)->with('status', 'Expense added.');
    }

    public function destroy(Group $group, Expense $expense): RedirectResponse
    {
        $this->authorize('addExpense', $group);

        abort_unless($expense->group_id === $group->id, 404);

        $expense->delete();

        return back()->with('status', 'Expense removed.');
    }
}
