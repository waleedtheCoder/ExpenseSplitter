<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $group->name }}</h2>
            @can('delete', $group)
                <form method="POST" action="{{ route('groups.destroy', $group) }}" onsubmit="return confirm('Delete this group? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button class="text-sm text-red-600 hover:underline">Delete Group</button>
                </form>
            @endcan
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-green-100 text-green-800 text-sm rounded-md p-4">{{ session('status') }}</div>
            @endif

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('expenses.create', $group) }}" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">
                    + Add Expense
                </a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Balances --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 font-medium text-gray-900">Balances</div>
                    <ul class="divide-y divide-gray-200">
                        @foreach ($balances as $b)
                            <li class="px-6 py-3 text-sm flex justify-between">
                                <span>{{ $b['user']->name }}</span>
                                @if ($b['amount'] > 0.004)
                                    <span class="text-green-700 font-medium">is owed ${{ number_format($b['amount'], 2) }}</span>
                                @elseif ($b['amount'] < -0.004)
                                    <span class="text-red-700 font-medium">owes ${{ number_format(abs($b['amount']), 2) }}</span>
                                @else
                                    <span class="text-gray-400">settled up</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Suggested settlements --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 font-medium text-gray-900">Suggested Payments</div>
                    <ul class="divide-y divide-gray-200">
                        @forelse ($transfers as $t)
                            <li class="px-6 py-3 text-sm flex justify-between items-center">
                                <span>{{ $t['from']->name }} → {{ $t['to']->name }}</span>
                                <div class="flex items-center gap-3">
                                    <span class="font-medium">${{ number_format($t['amount'], 2) }}</span>
                                    <form method="POST" action="{{ route('settlements.store', $group) }}">
                                        @csrf
                                        <input type="hidden" name="from_user_id" value="{{ $t['from']->id }}">
                                        <input type="hidden" name="to_user_id" value="{{ $t['to']->id }}">
                                        <input type="hidden" name="amount" value="{{ $t['amount'] }}">
                                        <button class="text-indigo-600 hover:underline text-xs">Mark Paid</button>
                                    </form>
                                </div>
                            </li>
                        @empty
                            <li class="px-6 py-3 text-sm text-gray-500">Everyone is settled up.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- Members --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 font-medium text-gray-900 flex justify-between items-center">
                    Members ({{ $group->members->count() }})
                </div>
                <ul class="divide-y divide-gray-200">
                    @foreach ($group->members as $member)
                        <li class="px-6 py-2 text-sm text-gray-700">{{ $member->name }} <span class="text-gray-400">{{ $member->email }}</span></li>
                    @endforeach
                </ul>

                @can('update', $group)
                    <form method="POST" action="{{ route('groups.members.add', $group) }}" class="px-6 py-4 border-t border-gray-200 flex gap-2">
                        @csrf
                        <input type="email" name="email" placeholder="member@example.com" required
                               class="flex-1 text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <button class="px-3 py-1.5 bg-gray-100 text-sm rounded-md hover:bg-gray-200">Add</button>
                    </form>
                @endcan
            </div>

            {{-- Expense history --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 font-medium text-gray-900">Expenses</div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paid By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Split Between</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($group->expenses->sortByDesc('created_at') as $expense)
                            <tr>
                                <td class="px-6 py-3 text-sm text-gray-900">{{ $expense->description }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500">{{ $expense->payer->name }}</td>
                                <td class="px-6 py-3 text-sm text-gray-900">${{ number_format($expense->amount, 2) }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500">{{ $expense->shares->count() }} people</td>
                                <td class="px-6 py-3 text-right">
                                    <form method="POST" action="{{ route('expenses.destroy', [$group, $expense]) }}" onsubmit="return confirm('Remove this expense?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs text-red-600 hover:underline">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-sm text-gray-500 text-center">No expenses logged yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Settlement history --}}
            @if ($group->settlements->isNotEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 font-medium text-gray-900">Settlement History</div>
                    <ul class="divide-y divide-gray-200">
                        @foreach ($group->settlements->sortByDesc('settled_at') as $settlement)
                            <li class="px-6 py-3 text-sm text-gray-700 flex justify-between">
                                <span>{{ $settlement->fromUser->name }} paid {{ $settlement->toUser->name }}</span>
                                <span class="font-medium">${{ number_format($settlement->amount, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
