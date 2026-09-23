@extends('layouts.app')
@section('title','500 — BacaDulu Research')
@section('content')<section class="error-page"><span>ERROR</span><h1>Something went wrong.</h1><p>The server encountered a problem while processing your request. Please try again shortly.</p><a class="btn btn-primary" href="{{ route('home') }}">Back to Home</a></section>@endsection
