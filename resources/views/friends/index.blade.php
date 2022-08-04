@extends('template')
@section('title', 'home')
@section('content')
    @include('friends.users', ['users' => $users])
@endsection
