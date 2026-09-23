@extends('layouts.app')
@section('title', 'My Profile — BacaDulu Research')
@section('content')
<section class="profile-page">
    <div class="profile-head" data-reveal>
        <div><p class="eyebrow">MY PROFILE</p><h1>Research profile.</h1><p>Keep identity and institution details current so your workspace stays accountable.</p></div>
        <div class="profile-score"><strong>{{ $user->profileCompletion() }}%</strong><span>complete</span></div>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif
    <div data-reveal>@include('components.profile-form', ['user' => $user, 'mode' => 'edit'])</div>
</section>
@endsection
