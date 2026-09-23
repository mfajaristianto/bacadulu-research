@php
    $isSetup = $mode === 'setup';
    $monthNames = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
@endphp
<form method="POST" action="{{ route('profile.update') }}" class="profile-form" enctype="multipart/form-data" data-profile-form>
    @csrf
    @method('PATCH')

    <div class="profile-photo-field full">
        <div class="profile-photo-preview" data-avatar-preview>
            <span data-avatar-fallback>{{ $user->initials }}</span>
            @if($user->avatar_url)
                <img src="{{ $user->avatar_url }}" alt="Current profile photo" class="profile-photo-preview-img" data-avatar-image decoding="async" referrerpolicy="no-referrer">
            @endif
        </div>
        <div class="profile-photo-copy">
            <label for="avatar">Profile photo</label>
            <p>JPG, PNG, atau WebP. Maksimum 3 MB. Preview akan berubah sebelum file disimpan.</p>
            <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-avatar-input>
        </div>
    </div>

    <label>Full name<input name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required></label>
    <label>Email address<input value="{{ $user->email }}" disabled></label>
    <label>Phone number <span class="field-optional">Optional</span><input name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel" inputmode="tel" placeholder="e.g. +62 812 3456 7890"></label>
    <label>Institution<input name="institution" value="{{ old('institution', $user->institution) }}" autocomplete="organization" required></label>

    <fieldset class="profile-fieldset">
        <legend>Date of birth</legend>
        <div class="dob-grid" data-dob-group>
            <select name="dob_month" aria-label="Birth month" required data-dob-month><option value="">Month</option>@foreach($monthNames as $monthNumber => $monthName)<option value="{{ $monthNumber }}" @selected(old('dob_month', $user->date_of_birth?->month) == $monthNumber)>{{ $monthName }}</option>@endforeach</select>
            <select name="dob_day" aria-label="Birth day" required data-dob-day><option value="">Day</option>@for($day = 1; $day <= 31; $day++)<option value="{{ $day }}" @selected(old('dob_day', $user->date_of_birth?->day) == $day)>{{ $day }}</option>@endfor</select>
            <select name="dob_year" aria-label="Birth year" required data-dob-year><option value="">Year</option>@for($year = now()->year; $year >= now()->year - 100; $year--)<option value="{{ $year }}" @selected(old('dob_year', $user->date_of_birth?->year) == $year)>{{ $year }}</option>@endfor</select>
        </div>
        <input type="hidden" name="date_of_birth" value="{{ old('date_of_birth', optional($user->date_of_birth)->format('Y-m-d')) }}" data-dob-output>
    </fieldset>

    <label class="full">Address<textarea name="address" rows="4" autocomplete="street-address" required>{{ old('address', $user->address) }}</textarea></label>
    <label class="check-row full"><input type="checkbox" name="terms" value="1" required checked><span>I agree to the platform terms and privacy policy.</span></label>

    <div class="profile-actions full">
        <button type="submit" class="btn btn-primary">{{ $isSetup ? 'Save Profile & Continue' : 'Save Profile' }}</button>
        @if(! $isSetup && $user->profileCompletion() >= config('research.profile_gate', 80))
            <a href="{{ route('workspace') }}" class="btn btn-secondary">Back to Workspace</a>
        @endif
    </div>
</form>
