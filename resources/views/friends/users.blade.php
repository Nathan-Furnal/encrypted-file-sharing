<div class="w-48 text-sm font-medium text-gray-900 bg-red border border-gray-200 rounded-md mr-2">
    @foreach ($users as $user)
        <a href="{{ route('home', $user->id) }}"
            class="block w-full px-4 py-2 border-b border-gray-200 cursor-pointer hover:bg-gray-100 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:text-blue-700">{{ $user->name }}</a>
    @endforeach
</div>