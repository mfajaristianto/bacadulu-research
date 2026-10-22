@extends('layouts.app')
@section('title','Maintenance — BacaDulu Research')
@section('content')<section class="error-page"><span>ERROR</span><h1>Research is under maintenance.</h1><p>The platform is temporarily unavailable while maintenance is being performed. Please check back shortly.</p><x-ui.button href="{{ route('home') }}">Back to Home</x-ui.button></section>@endsection
