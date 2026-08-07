<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use App\Services\BalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(Request $request): View
    {
        $groups = $request->user()->groups()->withCount('members')->get();

        return view('groups.index', compact('groups'));
    }

    public function create(): View
    {
        $this->authorize('create', Group::class);

        $users = User::orderBy('name')->get();

        return view('groups.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Group::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:users,id'],
        ]);

        $group = $request->user()->createdGroups()->create(['name' => $data['name']]);

        $memberIds = collect($data['member_ids'] ?? [])->push($request->user()->id)->unique();
        $group->members()->attach($memberIds);

        return redirect()->route('groups.show', $group)->with('status', 'Group created.');
    }

    public function show(Request $request, Group $group, BalanceService $balanceService): View
    {
        $this->authorize('view', $group);

        $group->load(['members', 'expenses.payer', 'expenses.shares', 'settlements.fromUser', 'settlements.toUser']);

        $balancesCents = $balanceService->balances($group);
        $transfersCents = $balanceService->simplify($balancesCents);

        $membersById = $group->members->keyBy('id');

        $balances = collect($balancesCents)->map(fn ($cents, $userId) => [
            'user' => $membersById->get($userId),
            'amount' => $cents / 100,
        ])->values();

        $transfers = collect($transfersCents)->map(fn ($t) => [
            'from' => $membersById->get($t['from']),
            'to' => $membersById->get($t['to']),
            'amount' => $t['amount'] / 100,
        ]);

        return view('groups.show', compact('group', 'balances', 'transfers'));
    }

    public function addMember(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);

        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();

        $group->members()->syncWithoutDetaching([$user->id]);

        return back()->with('status', $user->name.' added to the group.');
    }

    public function destroy(Group $group): RedirectResponse
    {
        $this->authorize('delete', $group);

        $group->delete();

        return redirect()->route('groups.index')->with('status', 'Group deleted.');
    }
}
