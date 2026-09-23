@extends('layouts.app')
@section('title', 'Complete Your Profile — BacaDulu Research')
@section('content')
<section class="profile-page">
    <div class="profile-head" data-reveal>
        <div><p class="eyebrow">PROFILE SETUP</p><h1>Complete your research profile.</h1><p>Lengkapi minimum {{ config('research.profile_gate', 80) }}% sebelum workspace dibuka.</p></div>
        <div class="profile-score"><strong>{{ $user->profileCompletion() }}%</strong><span>complete</span></div>
    </div>
    <div class="profile-progress-card" data-reveal>
        <div class="setup-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $user->profileCompletion() }}"><span data-progress-value="{{ min(100, $user->profileCompletion()) }}"></span></div>
        @if($user->missingProfileFields())<p>Still missing: <strong>{{ implode(', ', $user->missingProfileFields()) }}</strong></p>@else<p>Your required profile fields are complete.</p>@endif
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif
    <div data-reveal>@include('components.profile-form', ['user' => $user, 'mode' => 'setup'])</div>
</section>
@endsection
