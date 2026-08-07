<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Your Groups') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-green-100 text-green-800 text-sm rounded-md p-4">{{ session('status') }}</div>
            @endif

            <div class="text-right">
                <a href="{{ route('groups.create') }}" class="inline-block px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">
                    + New Group
                </a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <ul class="divide-y divide-gray-200">
                    @forelse ($groups as $group)
                        <li class="px-6 py-4 flex justify-between items-center">
                            <div>
                                <a href="{{ route('groups.show', $group) }}" class="text-indigo-600 hover:underline font-medium">{{ $group->name }}</a>
                                <span class="text-sm text-gray-400 ml-2">{{ $group->members_count }} member{{ $group->members_count === 1 ? '' : 's' }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="px-6 py-4 text-sm text-gray-500">You're not in any groups yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
