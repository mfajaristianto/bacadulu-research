@extends('layouts.app')
@section('title','429 — BacaDulu Research')
@section('content')<section class="error-page"><span>ERROR</span><h1>Too many requests.</h1><p>For security and reliability, requests are temporarily limited. Please try again shortly.</p><x-ui.button href="{{ route('home') }}">Back to Home</x-ui.button></section>@endsection
