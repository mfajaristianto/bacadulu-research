<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function setup(Request $request): View
    {
        return view('profile.setup', [
            'user' => $request->user(),
        ]);
    }

    public function avatar(Request $request): BinaryFileResponse
    {
        $avatar = trim(
            (string) $request->user()->getRawOriginal('avatar')
        );

        abort_if(
            $avatar === ''
            || Str::startsWith($avatar, ['http://', 'https://']),
            404
        );

        $normalized = ltrim($avatar, '/');

        $legacyStoragePath = Str::replaceStart(
            'storage/',
            '',
            $normalized
        );

        /*
         * Compatibility:
         * avatar lama yang pernah disimpan pada storage/app/public.
         */
        if (
            Str::startsWith($legacyStoragePath, 'profile-avatars/')
            && Storage::disk('public')->exists($legacyStoragePath)
        ) {
            return response()->file(
                Storage::disk('public')->path($legacyStoragePath),
                [
                    'Cache-Control' => 'private, max-age=3600',
                ]
            );
        }

        $filename = Str::replaceStart(
            'profile-avatars/',
            '',
            $legacyStoragePath
        );

        /*
         * Avatar baru.
         * Disimpan di private storage dan hanya diberikan melalui
         * authenticated route /profile/avatar.
         */
        if (
            $filename !== ''
            && Storage::disk('profile_avatars')->exists($filename)
        ) {
            return response()->file(
                Storage::disk('profile_avatars')->path($filename),
                [
                    'Cache-Control' => 'private, max-age=3600',
                ]
            );
        }

        /*
         * Compatibility:
         * versi project lama menyimpan avatar langsung di
         * public/uploads/profile-avatars.
         */
        $legacyPublicPath = public_path(
            'uploads/profile-avatars/'.$filename
        );

        abort_unless(
            $filename !== ''
            && File::isFile($legacyPublicPath),
            404
        );

        return response()->file(
            $legacyPublicPath,
            [
                'Cache-Control' => 'private, max-age=3600',
            ]
        );
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $context = $request->input('profile_context') === 'setup'
            ? 'setup'
            : 'edit';

        $data = $request->validate([
            'profile_context' => [
                'nullable',
                'in:setup,edit',
            ],

            'name' => [
                'required',
                'string',
                'max:120',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'institution' => [
                'nullable',
                'string',
                'max:180',
            ],

            'address' => [
                'nullable',
                'string',
                'max:500',
            ],

            'date_of_birth' => [
                'nullable',
                'date',
                'before:today',
            ],

            'avatar' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:3072',
            ],

            'terms' => [
                'nullable',
                'accepted_if:profile_context,setup',
            ],
        ]);

        unset(
            $data['profile_context'],
            $data['terms']
        );

        $oldAvatar = (string) $user->getRawOriginal('avatar');
        $newAvatar = null;

        /*
         * Upload avatar BARU dulu.
         *
         * Avatar lama belum disentuh sampai database berhasil disimpan.
         */
        if ($request->hasFile('avatar')) {
            $newAvatar = $request
                ->file('avatar')
                ->store('', 'profile_avatars');

            if (! $newAvatar) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'avatar' => 'Foto profil gagal disimpan. Silakan coba lagi.',
                    ]);
            }

            $data['avatar'] = $newAvatar;
        }

        try {
            $user->fill($data);
            $user->save();
        } catch (Throwable $exception) {
            /*
             * Jika database gagal setelah file baru sudah ter-upload,
             * hapus file BARU supaya tidak menjadi orphan file.
             */
            if ($newAvatar) {
                Storage::disk('profile_avatars')
                    ->delete($newAvatar);
            }

            throw $exception;
        }

        /*
         * Baru setelah database berhasil:
         * bersihkan avatar lama.
         */
        if ($newAvatar) {
            $this->deleteLocalAvatar(
                $oldAvatar,
                $newAvatar
            );
        }

        $completion = $user
            ->fresh()
            ->profileCompletion();

        $profileGate = (int) config(
            'research.profile_gate',
            80
        );

        if ($completion < $profileGate) {
            return redirect()
                ->route('profile.setup')
                ->with(
                    'success',
                    'Profil tersimpan. Lengkapi field yang masih kosong untuk membuka Research Workspace.'
                );
        }

        if ($context === 'edit') {
            return redirect()
                ->route('profile.edit')
                ->with(
                    'success',
                    'Perubahan profil berhasil disimpan.'
                );
        }

        return redirect()
            ->route('workspace')
            ->with(
                'success',
                'Profil lengkap. Research Workspace sudah dapat digunakan.'
            );
    }

    private function deleteLocalAvatar(
        string $avatar,
        ?string $except = null
    ): void {
        $avatar = trim($avatar);

        if (
            $avatar === ''
            || Str::startsWith($avatar, ['http://', 'https://'])
        ) {
            return;
        }

        $normalized = ltrim($avatar, '/');

        $legacyStoragePath = Str::replaceStart(
            'storage/',
            '',
            $normalized
        );

        $filename = Str::replaceStart(
            'profile-avatars/',
            '',
            $legacyStoragePath
        );

        /*
         * Jangan sampai file baru ikut terhapus.
         */
        if (
            $except !== null
            && $filename === $except
        ) {
            return;
        }

        /*
         * Legacy storage/app/public/profile-avatars.
         */
        if (
            Str::startsWith(
                $legacyStoragePath,
                'profile-avatars/'
            )
        ) {
            Storage::disk('public')
                ->delete($legacyStoragePath);
        }

        if ($filename === '') {
            return;
        }

        /*
         * Private avatar storage baru.
         */
        Storage::disk('profile_avatars')
            ->delete($filename);

        /*
         * Legacy direct public directory.
         */
        $legacyPublicPath = public_path(
            'uploads/profile-avatars/'.$filename
        );

        if (File::isFile($legacyPublicPath)) {
            File::delete($legacyPublicPath);
        }
    }
}
