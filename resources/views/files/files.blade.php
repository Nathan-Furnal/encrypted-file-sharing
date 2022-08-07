@section('title', 'Files')

@if (Session::has('message'))
<div class="alert alert-info">{{ Session::get('message') }}</div>
@endif
<x-app-layout>
	<x-slot name="header">
		<h2 class="font-semibold text-xl text-gray-800 leading-tight">
		</h2>
	</x-slot>

	<div class="py-12">
		<div class="w-auto mx-auto sm:px-6 lg:px-8">
			<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
				<div class="w-max p-6 bg-white border-b border-gray-200 flex ">
					<div class="mr-auto">
						<p class="text-center">Upload file</p>
						<div class="w-fit text-sm font-medium text-gray-900 bg-red border border-gray-200 rounded-md">
							<form action="/store/add" method="POST" enctype="multipart/form-data">
								@csrf
								<label for="inputFile">Choose a file</label>
								<input type="file" id="inputFile" name="user_file">
								<div class="col-md-12 text-center">
									<input type="submit" class="btn btn-dark">
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="w-auto mx-auto sm:px-6 lg:px-8">
			<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
				<div class="w-max p-6 bg-white border-b border-gray-200 flex ">
					<div class="mr-auto">
						<p class="text-center">File list</p>
						<div class="w-fit text-sm font-medium text-gray-900 bg-red border border-gray-200 rounded-md">
							@foreach ($files['files'] as $file)
							<div class="py-2" style="display: flex; flex:stretch; justify-content: space-around;">
								<form action="{{ route('download')}}" method="POST">
									@csrf
									<input type="hidden" name="path" value="{{$file['path']}}" />
									<input type="hidden" name="name" value="{{$file['name']}}" />
									<a href="#" onclick="this.parentNode.submit()" class="text-center block px-4 py-3 border-gray-200 cursor-pointer hover:bg-gray-100 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:text-blue-700">
										{{ $file['name'] }}
									</a>
								</form>
								<form action="/store/delete" method="POST">
									@csrf
									<input type="hidden" name="path" value="{{$file['path']}}" />
									<input name="" type="submit" value="Delete" class="block bg-red-500 text-white px-4 py-3 hover:bg-red-800" />
								</form>
								<form action="/store/share" method="POST">
									@csrf
									<label for="email" class="px-2">Share with: </label>
									<input id="email" name="email" type="text" value="" required placeholder="friend@gmail.com" />
									<input type="hidden" name="path" value="{{$file['path']}}" />
									<input type="hidden" name="name" value="{{$file['name']}}" />
									<input name="" type="submit" value="Share" class="bg-sky-400 text-white px-4 py-3 hover:bg-sky-700" />
								</form>
							</div>
							@endforeach
							@foreach ($files['sharedFiles'] as $file)
							<form action="{{ route('download')}}" method="POST">
								@csrf
								<input type="hidden" name="path" value="{{$file['path']}}" />
								<input type="hidden" name="name" value="{{$file['name']}}" />
								<a href="#" onclick="this.parentNode.submit()" class="text-center block px-4 py-3 border-gray-200 cursor-pointer hover:bg-gray-100 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:text-blue-700">
									{{ $file['name'] }}
								</a>
							</form>
							@endforeach
						</div>
					</div>
				</div>
			</div>
		</div>
</x-app-layout>
