<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettlementController extends Controller
{
    /** Record that money has changed hands to settle (part of) a debt. */
    public function store(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('addExpense', $group);

        $memberIds = $group->members->pluck('id');

        $data = $request->validate([
            'from_user_id' => ['required', 'integer', Rule::in($memberIds)],
            'to_user_id' => ['required', 'integer', Rule::in($memberIds), 'different:from_user_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $group->settlements()->create($data + ['settled_at' => now()]);

        return redirect()->route('groups.show', $group)->with('status', 'Settlement recorded.');
    }
}
