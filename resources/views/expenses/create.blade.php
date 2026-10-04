<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add Expense — {{ $group->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if ($aiEnabled)
                <div class="bg-indigo-50 border border-indigo-100 sm:rounded-lg p-6 space-y-5">
                    <div>
                        <h3 class="font-medium text-gray-900">Fill in with AI</h3>
                        <p class="text-xs text-gray-500">Nothing is saved until you check the form below and click Add Expense.</p>
                    </div>

                    <form method="POST" action="{{ route('expenses.ai.text', $group) }}">
                        @csrf
                        <x-input-label for="text" value="Describe it" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input id="text" name="text" class="flex-1" :value="old('text')" maxlength="500"
                                placeholder="e.g. I paid 45 for pizza with Bob and Carol" />
                            <x-secondary-button type="submit">Fill in</x-secondary-button>
                        </div>
                        <x-input-error :messages="$errors->get('text')" class="mt-2" />
                    </form>

                    <form method="POST" action="{{ route('expenses.ai.receipt', $group) }}" enctype="multipart/form-data">
                        @csrf
                        <x-input-label for="receipt" value="Or scan a receipt" />
                        <div class="flex gap-2 mt-1 items-center">
                            <input id="receipt" name="receipt" type="file" accept="image/*,application/pdf" capture="environment" required
                                   class="flex-1 text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-white file:text-sm">
                            <x-secondary-button type="submit">Scan</x-secondary-button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Photo or PDF, up to 5 MB.</p>
                        <x-input-error :messages="$errors->get('receipt')" class="mt-2" />
                    </form>
                </div>
            @endif

            @if (session('ai_status'))
                <div class="bg-green-100 text-green-800 text-sm rounded-md p-4">{{ session('ai_status') }}</div>
            @endif

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
                        <x-input-label for="category" value="Category" />
                        <select id="category" name="category" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">{{ $aiEnabled ? 'Auto (let AI choose)' : 'None' }}</option>
                            @foreach (\App\Models\Expense::CATEGORIES as $category)
                                <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('category')" class="mt-2" />
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
                                    <input type="checkbox" name="participant_ids[]" value="{{ $member->id }}" class="mr-2"
                                           @checked(in_array($member->id, array_map('intval', (array) old('participant_ids', $group->members->pluck('id')->all())), true))>
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
