@section('title', 'Files')

<x-app-layout>
	<x-slot name="header">
		<h2 class="font-semibold text-xl text-gray-800 leading-tight">
		</h2>
	</x-slot>

	<div class="py-12">
		<div class="w-auto mx-auto sm:px-6 lg:px-8">
			<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
				<div class="w-auto p-6 bg-white border-b border-gray-200">
					<p class="">
						To upload a file, use the form here under. Once a file is uploaded, you can download if by clicking on its name.
						If you are the file owner, more options are available, if the file is shared with you, you can only download it.
					</p>
					<p class="">
						To delete a file you own, click on the red button. To update the content of an existing file, select the new file from
						your storage, from the center form, and click the green button. Beware, it will overwrite the existing file! Also, you need to use
						the same file extension (MIME) as the old file.
					</p>
					<p class="">
						To share a file with someone from your contact list, add their email address to the right most form and click the blue button.
						Note that you will only be able to share with someone from your contact list.
					</p>

				</div>
				@if (Session::has('message'))
				<div class="alert alert-info">{{ Session::get('message') }}</div>
				@endif
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
							<div class="py-2" style="display: flex; flex: stretch;flex-wrap: wrap; justify-content: space-around;">
								<form action="{{ route('download')}}" method="POST">
									@csrf
									<input type="hidden" name="id" value="{{$file['id']}}" />
									<a href="#" onclick="this.parentNode.submit()" class="text-center block px-4 py-3 border-gray-200 cursor-pointer hover:bg-gray-100 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:text-blue-700">
										{{ $file['name'] }}
									</a>
								</form>
								<form action="/store/delete" method="POST">
									@csrf
									<input type="hidden" name="id" value="{{$file['id']}}" />
									<input name="" type="submit" value="Delete" class="block bg-red-500 text-white px-3 py-3 hover:bg-red-800" />
								</form>
								<form action="/store/edit" method="POST" enctype="multipart/form-data" class="flex mx-4">
									@csrf
									<input type="file" id="edit_file" name="edit_file" style="width: 70%;">
									<input type="hidden" name="id" value="{{$file['id']}}" />
									<input name="" type="submit" value="Edit" class=" block bg-green-500 text-white px-4 py-3 hover:bg-green-800" />
								</form>
								<form action="/store/share" method="POST">
									@csrf
									<label for="email" class="px-2">Share with: </label>
									<input id="email" name="email" type="text" value="" required placeholder="friend@gmail.com" />
									<input type="hidden" name="id" value="{{$file['id']}}" />
									<input name="" type="submit" value="Share" class="bg-sky-400 text-white px-4 py-3 hover:bg-sky-700" />
								</form>
								<form action="/store/sign" method="POST">
									@csrf
									<input type="hidden" name="id" value="{{$file['id']}}" />
									<input name="" type="submit" value="Signature" class="block bg-stone-400 text-white ml-5 px-4 py-3 hover:bg-neutral-800" />
								</form>
							</div>
							@endforeach
							@foreach ($files['sharedFiles'] as $file)
							<form action="{{ route('download')}}" method="POST">
								@csrf
								<input type="hidden" name="id" value="{{$file['id']}}" />
								<input type="hidden" name="owner" value="{{$file['owner']}}" />
								<input type="hidden" name="friend" value="{{$file['friend']}}" />
								<a href="#" onclick="this.parentNode.submit()" class="text-center block px-4 py-3 border-gray-200 cursor-pointer hover:bg-gray-100 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:text-blue-700">
									{{ $file['name'] }}
								</a>
							</form>
							<form action="/store/sign" method="POST">
								@csrf
								<input type="hidden" name="id" value="{{$file['id']}}" />
								<input type="hidden" name="owner" value="{{$file['owner']}}" />
								<input type="hidden" name="id" value="{{$file['friend']}}" />
								<input name="" type="submit" value="Signature" class="block bg-stone-400 text-white ml-5 px-4 py-3 hover:bg-neutral-800" />
							</form>
							@endforeach
						</div>
					</div>
				</div>
			</div>
		</div>
</x-app-layout>
