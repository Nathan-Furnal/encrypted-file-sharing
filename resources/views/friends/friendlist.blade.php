<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        </h2>
    </x-slot>
    <div class="py-12">

        <div class="w-50 mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">

                <div class="w-max p-6 bg-white border-b border-gray-200 flex ">
                    <div class="mr-auto">
                        <p class="text-center">Friends</p>
                        <div class="w-fit text-sm font-medium text-gray-900 bg-red border border-gray-200 rounded-md">
                            @foreach ($friends as $friend)
                                <a href="{{ route('home', $friend->id) }}"
                                    class="block text-center px-4 py-2 border-b border-gray-200 cursor-pointer hover:bg-gray-100 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:text-blue-700">{{ $friend->name }}</a>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <p class="text-center">Confirm friends</p>
                        <div class="w-fit text-sm font-medium text-gray-900 bg-red flex flex-column">
                            @foreach ($friendsPending as $friendPending)
                                <div class="flex flex-row rounded-md">
                                    <p class="alert alert-light border mb-0">
                                        {{ $friendPending->name }}</p>
                                    <a href="{{ route('friends.confirm', $friendPending->id) }}"
                                        class="alert alert-primary mb-0">confirm</a>
                                    <a href="{{ route('friends.reject', $friendPending->id) }}"
                                        class="alert alert-danger  mb-0">reject</a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
</x-app-layout>
