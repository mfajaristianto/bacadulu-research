@php
    $isSetup = $mode === 'setup';

    $monthNames = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    $completion = $user->profileCompletion();
@endphp

<form
    method="POST"
    action="{{ route('profile.update') }}"
    class="profile-form"
    enctype="multipart/form-data"
    data-profile-form
>
    @csrf
    @method('PATCH')

    <input
        type="hidden"
        name="profile_context"
        value="{{ $isSetup ? 'setup' : 'edit' }}"
    >

    <section class="profile-form-section profile-form-section--photo">
        <div class="profile-section-heading">
            <div>
                <span class="profile-section-number">01</span>

                <div>
                    <h2>Foto profil</h2>
                    <p>
                        Gunakan foto yang jelas atau tetap gunakan
                        inisial sebagai fallback.
                    </p>
                </div>
            </div>
        </div>

        <div class="profile-photo-field">
            <div
                class="profile-photo-preview"
                data-avatar-preview
            >
                <span
                    class="profile-avatar-initials"
                    data-avatar-fallback
                >
                    {{ $user->initials }}
                </span>

                @if ($user->avatar_url)
                    <img
                        src="{{ $user->avatar_url }}"
                        alt="Foto profil {{ $user->name }}"
                        class="profile-photo-preview-img"
                        data-avatar-image
                        decoding="async"
                        referrerpolicy="no-referrer"
                    >
                @endif
            </div>

            <div class="profile-photo-copy">
                <label for="avatar">
                    Upload / ganti foto
                </label>

                <p id="avatar-help">
                    JPG, JPEG, PNG, atau WebP.
                    Maksimum 3 MB.
                    Preview muncul sebelum disimpan.
                </p>

                <input
                    id="avatar"
                    type="file"
                    name="avatar"
                    accept="image/jpeg,image/png,image/webp"
                    aria-describedby="avatar-help"
                    data-avatar-input
                >

                @error('avatar')
                    <span class="field-error">
                        {{ $message }}
                    </span>
                @enderror
            </div>
        </div>
    </section>

    <section class="profile-form-section">
        <div class="profile-section-heading">
            <div>
                <span class="profile-section-number">02</span>

                <div>
                    <h2>Identitas</h2>
                    <p>
                        Informasi dasar untuk mengenali profil research Anda.
                    </p>
                </div>
            </div>
        </div>

        <div class="profile-fields-grid">
            <label for="profile-name">
                Nama lengkap

                <input
                    id="profile-name"
                    name="name"
                    value="{{ old('name', $user->name) }}"
                    autocomplete="name"
                    maxlength="120"
                    required
                >

                @error('name')
                    <span class="field-error">
                        {{ $message }}
                    </span>
                @enderror
            </label>

            <label for="profile-email">
                Email

                <input
                    id="profile-email"
                    value="{{ $user->email }}"
                    disabled
                >

                <small class="field-help">
                    Email mengikuti akun yang digunakan untuk login.
                </small>
            </label>

            <label for="profile-phone">
                Nomor telepon
                <span class="field-optional">Opsional</span>

                <input
                    id="profile-phone"
                    name="phone"
                    value="{{ old('phone', $user->phone) }}"
                    autocomplete="tel"
                    inputmode="tel"
                    maxlength="30"
                    placeholder="+62 812 3456 7890"
                >

                @error('phone')
                    <span class="field-error">
                        {{ $message }}
                    </span>
                @enderror
            </label>

            <label for="profile-institution">
                Institusi
                <span class="field-optional">Opsional</span>

                <input
                    id="profile-institution"
                    name="institution"
                    value="{{ old('institution', $user->institution) }}"
                    autocomplete="organization"
                    maxlength="180"
                    placeholder="Universitas / institusi / organisasi"
                >

                @error('institution')
                    <span class="field-error">
                        {{ $message }}
                    </span>
                @enderror
            </label>
        </div>
    </section>

    <section class="profile-form-section">
        <div class="profile-section-heading">
            <div>
                <span class="profile-section-number">03</span>

                <div>
                    <h2>Informasi tambahan</h2>
                    <p>
                        Data ini membantu melengkapi profil Anda dan tidak
                        ditampilkan sebagai public profile.
                    </p>
                </div>
            </div>
        </div>

        <div class="profile-fields-grid">
            <fieldset class="profile-fieldset">
                <legend>
                    Tanggal lahir
                    <span class="field-optional">Opsional</span>
                </legend>

                <div
                    class="dob-grid"
                    data-dob-group
                >
                    <select
                        name="dob_month"
                        aria-label="Bulan lahir"
                        data-dob-month
                    >
                        <option value="">Bulan</option>

                        @foreach ($monthNames as $monthNumber => $monthName)
                            <option
                                value="{{ $monthNumber }}"
                                @selected(
                                    old(
                                        'dob_month',
                                        $user->date_of_birth?->month
                                    ) == $monthNumber
                                )
                            >
                                {{ $monthName }}
                            </option>
                        @endforeach
                    </select>

                    <select
                        name="dob_day"
                        aria-label="Tanggal lahir"
                        data-dob-day
                    >
                        <option value="">Tanggal</option>

                        @for ($day = 1; $day <= 31; $day++)
                            <option
                                value="{{ $day }}"
                                @selected(
                                    old(
                                        'dob_day',
                                        $user->date_of_birth?->day
                                    ) == $day
                                )
                            >
                                {{ $day }}
                            </option>
                        @endfor
                    </select>

                    <select
                        name="dob_year"
                        aria-label="Tahun lahir"
                        data-dob-year
                    >
                        <option value="">Tahun</option>

                        @for (
                            $year = now()->year;
                            $year >= now()->year - 100;
                            $year--
                        )
                            <option
                                value="{{ $year }}"
                                @selected(
                                    old(
                                        'dob_year',
                                        $user->date_of_birth?->year
                                    ) == $year
                                )
                            >
                                {{ $year }}
                            </option>
                        @endfor
                    </select>
                </div>

                <input
                    type="hidden"
                    name="date_of_birth"
                    value="{{ old(
                        'date_of_birth',
                        $user->date_of_birth?->format('Y-m-d')
                    ) }}"
                    data-dob-output
                >

                @error('date_of_birth')
                    <span class="field-error">
                        {{ $message }}
                    </span>
                @enderror
            </fieldset>

            <label
                for="profile-address"
                class="profile-address-field"
            >
                Alamat
                <span class="field-optional">Opsional</span>

                <textarea
                    id="profile-address"
                    name="address"
                    rows="5"
                    autocomplete="street-address"
                    maxlength="500"
                    placeholder="Kota atau alamat yang ingin dicantumkan pada profil"
                >{{ old('address', $user->address) }}</textarea>

                @error('address')
                    <span class="field-error">
                        {{ $message }}
                    </span>
                @enderror
            </label>
        </div>
    </section>

    @if ($isSetup)
        <section class="profile-form-section profile-form-section--agreement">
            <label class="check-row profile-terms">
                <input
                    type="checkbox"
                    name="terms"
                    value="1"
                    @checked(old('terms'))
                    required
                >

                <span>
                    Saya menyetujui penggunaan akun sesuai ketentuan
                    dan kebijakan privasi BacaDulu Research.
                </span>
            </label>

            @error('terms')
                <span class="field-error">
                    {{ $message }}
                </span>
            @enderror
        </section>
    @endif

    <div class="profile-actions">
        <div class="profile-actions-copy">
            @if ($isSetup)
                <strong>
                    Kelengkapan saat ini {{ $completion }}%
                </strong>

                <span>
                    Minimum {{ config('research.profile_gate', 80) }}%
                    untuk membuka Research Workspace.
                </span>
            @else
                <strong>Simpan perubahan</strong>

                <span>
                    Perubahan avatar dan informasi profil akan
                    digunakan di seluruh workspace.
                </span>
            @endif
        </div>

        <div class="profile-actions-buttons">
            @if (
                ! $isSetup
                && $completion >= config('research.profile_gate', 80)
            )
                <a
                    href="{{ route('workspace') }}"
                    class="btn btn-secondary"
                >
                    Kembali
                </a>
            @endif

            <button
                type="submit"
                class="btn btn-primary"
                data-motion-button
            >
                {{ $isSetup ? 'Simpan & Lanjutkan' : 'Simpan Perubahan' }}
            </button>
        </div>
    </div>
</form>