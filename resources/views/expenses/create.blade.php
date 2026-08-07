<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add Expense — {{ $group->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('expenses.store', $group) }}">
                    @csrf

                    <div>
                        <x-input-label for="description" value="What was it for?" />
                        <x-text-input id="description" name="description" class="block mt-1 w-full" :value="old('description')" placeholder="Dinner, groceries, tickets..." required autofocus />
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="amount" value="Amount ($)" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01" class="block mt-1 w-full" :value="old('amount')" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="paid_by" value="Paid By" />
                        <select id="paid_by" name="paid_by" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                            @foreach ($group->members as $member)
                                <option value="{{ $member->id }}" {{ old('paid_by', auth()->id()) == $member->id ? 'selected' : '' }}>
                                    {{ $member->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('paid_by')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label value="Split Equally Between" />
                        <p class="text-xs text-gray-500 mb-2">The amount is divided evenly across everyone you check.</p>
                        <div class="space-y-1">
                            @foreach ($group->members as $member)
                                <label class="flex items-center text-sm">
                                    <input type="checkbox" name="participant_ids[]" value="{{ $member->id }}" checked class="mr-2">
                                    {{ $member->name }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('participant_ids')" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-between">
                        <a href="{{ route('groups.show', $group) }}" class="text-sm text-gray-600 hover:underline self-center">Cancel</a>
                        <x-primary-button>Add Expense</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
