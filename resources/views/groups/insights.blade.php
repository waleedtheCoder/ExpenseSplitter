<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Insights — {{ $group->name }}</h2>
            <a href="{{ route('groups.show', $group) }}" class="text-sm text-gray-600 hover:underline">Back to group</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="flex justify-between items-center">
                <a href="{{ route('groups.insights', [$group, 'month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="text-sm text-indigo-600 hover:underline">← {{ $month->copy()->subMonth()->format('M Y') }}</a>
                <div class="font-medium text-gray-900">{{ $stats['month'] }}</div>
                @if ($month->lt(now()->startOfMonth()))
                    <a href="{{ route('groups.insights', [$group, 'month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="text-sm text-indigo-600 hover:underline">{{ $month->copy()->addMonth()->format('M Y') }} →</a>
                @else
                    <span></span>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-xs text-gray-500 uppercase">Spent this month</div>
                    <div class="text-2xl font-semibold text-gray-900 mt-1">${{ number_format($stats['total'], 2) }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-xs text-gray-500 uppercase">Previous month</div>
                    <div class="text-2xl font-semibold text-gray-900 mt-1">${{ number_format($stats['previous_month_total'], 2) }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-xs text-gray-500 uppercase">Expenses logged</div>
                    <div class="text-2xl font-semibold text-gray-900 mt-1">{{ $stats['expense_count'] }}</div>
                </div>
            </div>

            {{-- AI summary --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-center">
                    <div class="font-medium text-gray-900">AI summary</div>
                    @if ($aiEnabled && $stats['expense_count'] > 0)
                        <form method="POST" action="{{ route('groups.insights.generate', $group) }}">
                            @csrf
                            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                            <button class="text-sm text-indigo-600 hover:underline">{{ $summary ? 'Regenerate' : 'Generate summary' }}</button>
                        </form>
                    @endif
                </div>

                <x-input-error :messages="$errors->get('summary')" class="mt-2" />

                @if ($summary)
                    <p class="mt-3 text-gray-900">{{ $summary['headline'] }}</p>
                    <ul class="mt-3 list-disc list-inside text-sm text-gray-700 space-y-1">
                        @foreach ($summary['highlights'] as $highlight)
                            <li>{{ $highlight }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-sm text-gray-600"><span class="font-medium">Tip:</span> {{ $summary['tip'] }}</p>
                @elseif ($stats['expense_count'] === 0)
                    <p class="mt-3 text-sm text-gray-500">No expenses this month.</p>
                @elseif (! $aiEnabled)
                    <p class="mt-3 text-sm text-gray-500">Set ANTHROPIC_API_KEY in .env to enable AI summaries.</p>
                @else
                    <p class="mt-3 text-sm text-gray-500">Click "Generate summary" for an AI write-up of this month's spending.</p>
                @endif
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 font-medium text-gray-900">By Category</div>
                    <ul class="divide-y divide-gray-200">
                        @forelse ($stats['by_category'] as $category => $amount)
                            @php($previous = $stats['previous_month_by_category'][$category] ?? 0)
                            <li class="px-6 py-3 text-sm">
                                <div class="flex justify-between">
                                    <span>{{ $category }}</span>
                                    <span class="font-medium">${{ number_format($amount, 2) }}</span>
                                </div>
                                <div class="mt-1 h-1.5 bg-gray-100 rounded">
                                    <div class="h-1.5 bg-indigo-500 rounded" style="width: {{ $stats['total'] > 0 ? round($amount / $stats['total'] * 100) : 0 }}%"></div>
                                </div>
                                @if ($previous > 0)
                                    <div class="text-xs text-gray-500 mt-1">${{ number_format($previous, 2) }} last month</div>
                                @endif
                            </li>
                        @empty
                            <li class="px-6 py-3 text-sm text-gray-500">Nothing yet.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 font-medium text-gray-900">Who Paid</div>
                    <ul class="divide-y divide-gray-200">
                        @forelse ($stats['paid_by_member'] as $name => $amount)
                            <li class="px-6 py-3 text-sm flex justify-between">
                                <span>{{ $name }}</span>
                                <span class="font-medium">${{ number_format($amount, 2) }}</span>
                            </li>
                        @empty
                            <li class="px-6 py-3 text-sm text-gray-500">Nothing yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
