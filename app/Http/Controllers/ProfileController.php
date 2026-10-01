<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Profil akun yang sedang login. Semua aksi selalu mengenai Auth::user() —
 * tidak ada parameter id — sehingga user tidak bisa mengubah akun lain.
 * Username, role, divisi & hak akses tetap dikelola Admin lewat User Management.
 */
class ProfileController extends Controller
{
    public function edit()
    {
        $user = Auth::user()->load(['division', 'hierarchyRole']);

        return view('profile.edit', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'full_name' => 'required|string|max:150',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'phone_number' => 'nullable|string|max:20',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'photo.image' => 'Foto harus berupa gambar.',
            'photo.mimes' => 'Foto harus berformat JPG, PNG, atau WEBP.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
        ]);

        $data = [
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'] ?? null,
        ];

        if ($request->hasFile('photo')) {
            $this->deleteStoredPhoto($user->icon);
            $data['icon'] = $request->file('photo')->store('avatars', 'public');
        }

        $user->update($data);

        return redirect()->route('profile.edit')
            ->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('password', [
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:6|confirmed|different:current_password',
        ], [
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
        ]);

        Auth::user()->update(['password' => $validated['password']]);

        return redirect()->route('profile.edit')
            ->with('success', 'Password berhasil diubah.');
    }

    public function destroyPhoto(): RedirectResponse
    {
        $user = Auth::user();

        $this->deleteStoredPhoto($user->icon);
        $user->update(['icon' => null]);

        return redirect()->route('profile.edit')
            ->with('success', 'Foto profil berhasil dihapus.');
    }

    /**
     * Hanya hapus file yang memang kita simpan di disk public (path relatif).
     * Nilai icon berupa URL eksternal dibiarkan.
     */
    private function deleteStoredPhoto(?string $icon): void
    {
        if ($icon && ! preg_match('#^(https?:)?/#i', $icon)) {
            Storage::disk('public')->delete($icon);
        }
    }
}
