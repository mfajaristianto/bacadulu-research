@extends('layouts.app')
@section('title','429 — BacaDulu Research')
@section('content')<section class="error-page"><span>ERROR</span><h1>Too many requests.</h1><p>For security and reliability, requests are temporarily limited. Please try again shortly.</p><a class="btn btn-primary" href="{{ route('home') }}">Back to Home</a></section>@endsection
