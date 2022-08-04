<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        </h2>
    </x-slot>
    <div class="py-12">
        <div class="w-25 mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="w-max p-6 bg-white border-b border-gray-200 flex">
                    <div class="mr-auto">
                        <p class="text-center">Users</p>
                        <div
                            class="w-max text-sm font-medium text-gray-900 bg-red flex flex-column">

                            @foreach ($users as $user)
                                <div class="flex flex-row rounded-md">
                                    <p class="alert alert-light border mb-0">{{ $user->name }}</p>
                                    <a href="{{ route('friends.add', $user->id) }}"
                                        class="alert alert-primary mb-0">add</a>
                                </div>
                            @endforeach

                        </div>
                    </div>
                </div>
            </div>
        </div>
</x-app-layout>
