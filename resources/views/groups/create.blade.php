<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('New Group') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('groups.store') }}">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Group Name" />
                        <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name')" placeholder="Roommates, Goa Trip, ..." required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label value="Members" />
                        <p class="text-xs text-gray-500 mb-2">You're added automatically. Pick anyone else already registered.</p>
                        <div class="space-y-1 max-h-48 overflow-y-auto border border-gray-200 rounded-md p-3">
                            @foreach ($users as $user)
                                @if ($user->id !== auth()->id())
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="member_ids[]" value="{{ $user->id }}" class="mr-2">
                                        {{ $user->name }} <span class="text-gray-400 ml-1">{{ $user->email }}</span>
                                    </label>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <x-primary-button>Create Group</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
