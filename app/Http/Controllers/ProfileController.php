<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    // PROFILE SCREENS
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function setup(Request $request): View
    {
        return view('profile.setup', ['user' => $request->user()]);
    }

    // PROFILE AVATAR DELIVERY
    public function avatar(Request $request): BinaryFileResponse
    {
        $avatar = trim((string) $request->user()->getRawOriginal('avatar'));

        abort_if($avatar === '' || Str::startsWith($avatar, ['http://', 'https://']), 404);

        $normalized = ltrim($avatar, '/');
        $legacyPath = Str::replaceStart('storage/', '', $normalized);

        if (Str::startsWith($legacyPath, 'profile-avatars/') && Storage::disk('public')->exists($legacyPath)) {
            return response()->file(
                Storage::disk('public')->path($legacyPath),
                ['Cache-Control' => 'private, max-age=3600']
            );
        }

        $newPath = Str::replaceStart('profile-avatars/', '', $legacyPath);

        abort_unless(Storage::disk('profile_avatars')->exists($newPath), 404);

        return response()->file(
            Storage::disk('profile_avatars')->path($newPath),
            ['Cache-Control' => 'private, max-age=3600']
        );
    }

    // PROFILE UPDATE
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'institution' => ['required', 'string', 'max:180'],
            'address' => ['required', 'string', 'max:500'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'terms' => ['accepted'],
        ]);

        unset($data['terms']);

        if ($request->hasFile('avatar')) {
            $this->deleteLocalAvatar((string) $user->avatar);
            $data['avatar'] = $request->file('avatar')->store('', 'profile_avatars');
        }

        $user->update($data);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Profile berhasil disimpan.');
    }

    private function deleteLocalAvatar(string $avatar): void
    {
        $avatar = trim($avatar);

        if ($avatar === '' || Str::startsWith($avatar, ['http://', 'https://'])) {
            return;
        }

        $normalized = ltrim($avatar, '/');
        $legacyPath = Str::replaceStart('storage/', '', $normalized);

        if (Str::startsWith($legacyPath, 'profile-avatars/')) {
            Storage::disk('public')->delete($legacyPath);
        }

        $newPath = Str::replaceStart('profile-avatars/', '', $legacyPath);
        Storage::disk('profile_avatars')->delete($newPath);
    }
}
